"use client";

import { BookingDetailSheet } from "@/components/admin/booking-detail-sheet";
import { BookingFormDialog } from "@/components/admin/booking-form-dialog";
import { PageHeader } from "@/components/shared/page-header";
import { BookingStatusBadge } from "@/components/shared/status-badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import { formatDate, formatTime } from "@/lib/format";
import { BOOKING_STATUS } from "@/lib/status";
import { cn } from "@/lib/utils";
import type { Booking, BookingStatus } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  addMonths, eachDayOfInterval, endOfMonth, endOfWeek, format, isSameDay, isSameMonth,
  startOfMonth, startOfWeek,
} from "date-fns";
import { CalendarPlus, ChevronLeft, ChevronRight, Plus } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";

interface ScheduleDay { date: string; bookings: Booking[] }

const STATUS_DOT: Record<BookingStatus, string> = {
  pending: "bg-amber-400", confirmed: "bg-green-500", in_progress: "bg-blue-500",
  completed: "bg-teal-500", cancelled: "bg-red-400",
};
const STATUS_CHIP: Record<BookingStatus, string> = {
  pending: "border-amber-400 bg-amber-50 text-amber-900",
  confirmed: "border-green-500 bg-green-50 text-green-900",
  in_progress: "border-blue-500 bg-blue-50 text-blue-900",
  completed: "border-teal-500 bg-teal-50 text-teal-900",
  cancelled: "border-red-400 bg-red-50 text-red-800 line-through",
};

export default function AdminSchedulePage() {
  const qc = useQueryClient();
  const [cursor, setCursor] = useState(new Date());
  const [selected, setSelected] = useState<string | null>(null);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [createDate, setCreateDate] = useState<string | undefined>(undefined);
  const [showCreate, setShowCreate] = useState(false);
  const [dragOver, setDragOver] = useState<string | null>(null);

  const gridStart = startOfWeek(startOfMonth(cursor), { weekStartsOn: 1 });
  const gridEnd = endOfWeek(endOfMonth(cursor), { weekStartsOn: 1 });
  const days = eachDayOfInterval({ start: gridStart, end: gridEnd });

  const { data } = useQuery({
    queryKey: ["admin", "schedule", format(gridStart, "yyyy-MM-dd"), format(gridEnd, "yyyy-MM-dd")],
    queryFn: () => apiGet<ScheduleDay[]>("admin/schedule", { params: { from: format(gridStart, "yyyy-MM-dd"), to: format(gridEnd, "yyyy-MM-dd") } }),
  });

  const byDate = useMemo(() => {
    const map = new Map<string, Booking[]>();
    data?.forEach((d) => map.set(d.date, d.bookings));
    return map;
  }, [data]);

  const selectedBookings = selected ? byDate.get(selected) ?? [] : [];

  const reschedule = useMutation({
    mutationFn: ({ id, date }: { id: number; date: string }) =>
      apiSend("patch", `admin/bookings/${id}`, { scheduled_date: date }),
    onSuccess: (_res, vars) => {
      toast.success(`Job moved to ${formatDate(vars.date, "d MMM yyyy")}.`);
      qc.invalidateQueries({ queryKey: ["admin", "schedule"] });
      qc.invalidateQueries({ queryKey: ["admin", "bookings"] });
      qc.invalidateQueries({ queryKey: ["admin", "booking"] });
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not move this job."),
  });

  const openCreate = (date?: string) => { setCreateDate(date); setShowCreate(true); };

  const onDrop = (dateKey: string, e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(null);
    const id = Number(e.dataTransfer.getData("text/plain"));
    if (!id) return;
    const from = data?.find((d) => d.bookings.some((b) => b.id === id));
    const booking = from?.bookings.find((b) => b.id === id);
    if (!booking || booking.scheduled_date === dateKey) return;
    reschedule.mutate({ id, date: dateKey });
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Schedule"
        subtitle={format(cursor, "MMMM yyyy")}
        actions={
          <div className="flex items-center gap-2">
            <Button variant="outline" size="icon" onClick={() => setCursor(addMonths(cursor, -1))}><ChevronLeft className="h-4 w-4" /></Button>
            <Button variant="outline" size="sm" onClick={() => setCursor(new Date())}>Today</Button>
            <Button variant="outline" size="icon" onClick={() => setCursor(addMonths(cursor, 1))}><ChevronRight className="h-4 w-4" /></Button>
            <Button size="sm" onClick={() => openCreate(undefined)}><CalendarPlus className="h-4 w-4" /> New booking</Button>
          </div>
        }
      />

      <p className="text-xs text-muted-foreground">Tip: drag a job to another day to reschedule it, click the <Plus className="inline h-3 w-3" /> on a day to add one, or click a job to open it.</p>

      <Card className="overflow-hidden rounded-2xl">
        <div className="grid grid-cols-7 border-b bg-muted/40 text-center text-xs font-medium text-muted-foreground">
          {["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].map((d) => <div key={d} className="py-2">{d}</div>)}
        </div>
        <div className="grid grid-cols-7">
          {days.map((day) => {
            const key = format(day, "yyyy-MM-dd");
            const items = (byDate.get(key) ?? []).slice().sort((a, b) => a.start_time.localeCompare(b.start_time));
            const inMonth = isSameMonth(day, cursor);
            const isToday = isSameDay(day, new Date());
            return (
              <div
                key={key}
                onClick={() => setSelected(key)}
                onDragOver={(e) => { e.preventDefault(); setDragOver(key); }}
                onDragLeave={() => setDragOver((k) => (k === key ? null : k))}
                onDrop={(e) => onDrop(key, e)}
                className={cn(
                  "group relative min-h-28 cursor-pointer border-b border-r p-1.5 text-left align-top transition-colors",
                  !inMonth && "bg-muted/20 text-muted-foreground",
                  dragOver === key ? "bg-primary/10 ring-2 ring-inset ring-primary/40" : "hover:bg-muted/40",
                )}
              >
                <div className="flex items-center justify-between">
                  <span className={cn("text-sm", isToday && "flex h-6 w-6 items-center justify-center rounded-full bg-primary text-primary-foreground")}>
                    {format(day, "d")}
                  </span>
                  <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); openCreate(key); }}
                    title="Add a job on this day"
                    className="flex h-5 w-5 items-center justify-center rounded-md text-muted-foreground opacity-0 transition hover:bg-primary hover:text-primary-foreground group-hover:opacity-100"
                  >
                    <Plus className="h-3.5 w-3.5" />
                  </button>
                </div>
                <div className="mt-1 space-y-1">
                  {items.slice(0, 3).map((b) => (
                    <button
                      key={b.id}
                      draggable
                      onDragStart={(e) => { e.stopPropagation(); e.dataTransfer.setData("text/plain", String(b.id)); e.dataTransfer.effectAllowed = "move"; }}
                      onClick={(e) => { e.stopPropagation(); setDetailId(b.id); }}
                      title={`${formatTime(b.start_time)} · ${b.service?.name ?? ""} — ${b.customer?.name ?? ""}`}
                      className={cn("flex w-full items-center gap-1 truncate rounded-md border-l-2 px-1.5 py-0.5 text-left text-[11px] leading-tight", STATUS_CHIP[b.status])}
                    >
                      <span className="font-medium">{formatTime(b.start_time)}</span>
                      <span className="truncate">{b.service?.name}</span>
                    </button>
                  ))}
                  {items.length > 3 ? (
                    <button
                      onClick={(e) => { e.stopPropagation(); setSelected(key); }}
                      className="w-full rounded-md px-1.5 text-left text-[11px] font-medium text-muted-foreground hover:text-foreground"
                    >
                      +{items.length - 3} more
                    </button>
                  ) : null}
                </div>
              </div>
            );
          })}
        </div>
      </Card>

      <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
        {(Object.keys(BOOKING_STATUS) as BookingStatus[]).map((s) => (
          <span key={s} className="flex items-center gap-1.5"><span className={cn("h-2 w-2 rounded-full", STATUS_DOT[s])} />{BOOKING_STATUS[s].label}</span>
        ))}
      </div>

      <Sheet open={selected !== null} onOpenChange={(o) => !o && setSelected(null)}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-md">
          <SheetHeader><SheetTitle>{selected ? formatDate(selected, "EEEE, d MMM yyyy") : ""}</SheetTitle></SheetHeader>
          <div className="space-y-2 py-4">
            <Button className="w-full" size="sm" onClick={() => { if (selected) openCreate(selected); setSelected(null); }}>
              <CalendarPlus className="h-4 w-4" /> Add a job on this day
            </Button>
            {selectedBookings.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">No jobs scheduled.</p>
            ) : (
              selectedBookings.slice().sort((a, b) => a.start_time.localeCompare(b.start_time)).map((b) => (
                <button
                  key={b.id}
                  onClick={() => { setDetailId(b.id); setSelected(null); }}
                  className="w-full rounded-xl border px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted/40"
                >
                  <div className="flex items-center justify-between">
                    <span className="font-medium">{formatTime(b.start_time)} · {b.service?.name}</span>
                    <BookingStatusBadge status={b.status} />
                  </div>
                  <p className="text-xs text-muted-foreground">{b.customer?.name} · {b.cleaner?.name ?? "Unassigned"}</p>
                </button>
              ))
            )}
          </div>
        </SheetContent>
      </Sheet>

      <BookingFormDialog open={showCreate} onOpenChange={setShowCreate} initialDate={createDate} />
      <BookingDetailSheet bookingId={detailId} open={detailId !== null} onOpenChange={(o) => !o && setDetailId(null)} />
    </div>
  );
}
