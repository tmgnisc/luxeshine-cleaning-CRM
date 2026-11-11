"use client";

import { AssignCleanerDialog } from "@/components/admin/assign-cleaner-dialog";
import { BookingDetailSheet } from "@/components/admin/booking-detail-sheet";
import { BookingFormDialog } from "@/components/admin/booking-form-dialog";
import { PageHeader } from "@/components/shared/page-header";
import { Pagination } from "@/components/shared/pagination";
import { LoadingRows, EmptyState, ErrorState } from "@/components/shared/states";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useCurrency } from "@/hooks/use-settings";
import { apiGet, apiGetPage, apiSend } from "@/lib/api";
import { formatDate, formatTime } from "@/lib/format";
import { BOOKING_STATUS, NEXT_STATUSES } from "@/lib/status";
import type { Booking, BookingStatus } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { cn } from "@/lib/utils";
import { CalendarPlus, Download, Eye, Trash2, UserPlus } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

const STATUS_KEYS: BookingStatus[] = ["pending", "confirmed", "in_progress", "completed", "cancelled"];

export default function AdminBookingsPage() {
  const qc = useQueryClient();
  const currency = useCurrency();

  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<string>("all");
  const [search, setSearch] = useState("");
  const [unassigned, setUnassigned] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [assignFor, setAssignFor] = useState<Booking | null>(null);

  const params = {
    page,
    per_page: 15,
    ...(status !== "all" ? { status } : {}),
    ...(search ? { search } : {}),
    ...(unassigned ? { unassigned: true } : {}),
  };

  const list = useQuery({
    queryKey: ["admin", "bookings", params],
    queryFn: () => apiGetPage<Booking>("admin/bookings", { params }),
  });

  const statusCounts = useQuery({
    queryKey: ["admin", "status-counts"],
    queryFn: () => apiGet<Record<string, number>>("admin/bookings/status-counts"),
  });

  const changeStatus = useMutation({
    mutationFn: ({ id, next }: { id: number; next: BookingStatus }) =>
      apiSend("patch", `admin/bookings/${id}/status`, {
        status: next,
        cancellation_reason: next === "cancelled" ? "Cancelled by admin" : undefined,
      }),
    onSuccess: () => {
      toast.success("Status updated.");
      qc.invalidateQueries({ queryKey: ["admin", "bookings"] });
      qc.invalidateQueries({ queryKey: ["admin", "status-counts"] });
    },
    onError: (e: unknown) => toast.error(e instanceof Error ? e.message : "Transition not allowed."),
  });

  const togglePaid = useMutation({
    mutationFn: (id: number) => apiSend("patch", `admin/bookings/${id}/paid`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "bookings"] }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => apiSend("delete", `admin/bookings/${id}`),
    onSuccess: () => {
      toast.success("Booking deleted.");
      qc.invalidateQueries({ queryKey: ["admin", "bookings"] });
    },
  });

  const exportCsv = () => {
    const qs = new URLSearchParams(
      Object.entries(params).filter(([k]) => !["page", "per_page"].includes(k)).map(([k, v]) => [k, String(v)]),
    );
    window.open(`/bff/admin/bookings/export?${qs.toString()}`, "_blank");
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Bookings"
        subtitle={list.data ? `${list.data.data.length} of ${list.data.meta.total} bookings shown` : "Manage all bookings"}
        actions={
          <>
            <Button variant="outline" onClick={exportCsv}>
              <Download className="h-4 w-4" /> Export CSV
            </Button>
            <Button onClick={() => setShowNew(true)}>
              <CalendarPlus className="h-4 w-4" /> New booking
            </Button>
          </>
        }
      />

      {/* Status chips */}
      <div className="flex flex-wrap gap-2">
        <Chip active={status === "all"} onClick={() => { setStatus("all"); setPage(1); }}>
          All {statusCounts.data ? `(${statusCounts.data.total ?? 0})` : ""}
        </Chip>
        {STATUS_KEYS.map((k) => (
          <Chip key={k} active={status === k} onClick={() => { setStatus(k); setPage(1); }} className={BOOKING_STATUS[k].className}>
            {BOOKING_STATUS[k].label} {statusCounts.data ? `(${statusCounts.data[k] ?? 0})` : ""}
          </Chip>
        ))}
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <Input
          placeholder="Search by reference or customer…"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          className="max-w-xs"
        />
        <label className="flex items-center gap-2 text-sm text-muted-foreground">
          <Switch checked={unassigned} onCheckedChange={(v) => { setUnassigned(v); setPage(1); }} />
          Unassigned only
        </label>
      </div>

      {list.isLoading ? (
        <LoadingRows />
      ) : list.isError ? (
        <ErrorState onRetry={() => list.refetch()} />
      ) : list.data && list.data.data.length === 0 ? (
        <EmptyState title="No bookings found" description="Try adjusting your filters, or create a new booking." icon={CalendarPlus} />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Reference</TableHead>
                <TableHead>Date &amp; time</TableHead>
                <TableHead>Service</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Cleaner</TableHead>
                <TableHead>Price</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Paid</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.data!.data.map((b) => (
                <TableRow key={b.id}>
                  <TableCell className="font-medium">{b.reference}</TableCell>
                  <TableCell className="whitespace-nowrap">
                    {formatDate(b.scheduled_date)}
                    <span className="block text-xs text-muted-foreground">
                      {formatTime(b.start_time)} · {b.duration_hours}h
                    </span>
                  </TableCell>
                  <TableCell>{b.service?.name}</TableCell>
                  <TableCell>{b.customer?.name}</TableCell>
                  <TableCell>
                    <button
                      onClick={() => setAssignFor(b)}
                      className={cn("text-left text-sm hover:underline", !b.cleaner && "text-primary")}
                    >
                      {b.cleaner?.name ?? "Assign…"}
                    </button>
                  </TableCell>
                  <TableCell className="whitespace-nowrap">{currency(b.total_price)}</TableCell>
                  <TableCell>
                    <StatusSelect
                      status={b.status}
                      onChange={(next) => changeStatus.mutate({ id: b.id, next })}
                    />
                  </TableCell>
                  <TableCell>
                    <Switch checked={b.is_paid} onCheckedChange={() => togglePaid.mutate(b.id)} />
                  </TableCell>
                  <TableCell>
                    <div className="flex justify-end gap-1">
                      <Button size="icon" variant="ghost" onClick={() => setDetailId(b.id)}>
                        <Eye className="h-4 w-4" />
                      </Button>
                      <Button size="icon" variant="ghost" onClick={() => setAssignFor(b)}>
                        <UserPlus className="h-4 w-4" />
                      </Button>
                      <Button
                        size="icon"
                        variant="ghost"
                        className="text-destructive"
                        onClick={() => {
                          if (confirm(`Delete booking ${b.reference}?`)) remove.mutate(b.id);
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <Pagination meta={list.data?.meta} onPage={setPage} />

      <BookingFormDialog open={showNew} onOpenChange={setShowNew} />
      <BookingDetailSheet bookingId={detailId} open={detailId !== null} onOpenChange={(o) => !o && setDetailId(null)} />
      <AssignCleanerDialog booking={assignFor} open={assignFor !== null} onOpenChange={(o) => !o && setAssignFor(null)} />
    </div>
  );
}

function StatusSelect({ status, onChange }: { status: BookingStatus; onChange: (next: BookingStatus) => void }) {
  const options = NEXT_STATUSES[status];
  if (options.length === 0) {
    return <Badge variant="outline" className={BOOKING_STATUS[status].className}>{BOOKING_STATUS[status].label}</Badge>;
  }
  return (
    <Select value={status} onValueChange={(v) => onChange(v as BookingStatus)}>
      <SelectTrigger className="h-8 w-[140px]">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={status} disabled>{BOOKING_STATUS[status].label}</SelectItem>
        {options.map((o) => (
          <SelectItem key={o} value={o}>{BOOKING_STATUS[o].label}</SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

function Chip({ active, onClick, children, className }: { active: boolean; onClick: () => void; children: React.ReactNode; className?: string }) {
  return (
    <button
      onClick={onClick}
      className={cn(
        "rounded-full border px-3 py-1 text-xs font-medium transition-colors",
        active ? "border-primary bg-primary text-primary-foreground" : cn("border-border bg-card text-foreground hover:bg-muted", className),
      )}
    >
      {children}
    </button>
  );
}
