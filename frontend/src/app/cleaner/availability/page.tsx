"use client";

import { LoadingRows } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import { formatDate } from "@/lib/format";
import { DAY_LABELS } from "@/lib/status";
import type { TimeOff, WorkingHour } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export default function CleanerAvailabilityPage() {
  const qc = useQueryClient();
  const [hours, setHours] = useState<WorkingHour[]>([]);

  const wh = useQuery({ queryKey: ["cleaner", "working-hours"], queryFn: () => apiGet<WorkingHour[]>("cleaner/working-hours") });
  const timeOff = useQuery({ queryKey: ["cleaner", "time-off"], queryFn: () => apiGet<TimeOff[]>("cleaner/time-off") });

  useEffect(() => {
    if (wh.data) {
      const full: WorkingHour[] = [];
      for (let d = 0; d <= 6; d++) {
        const existing = wh.data.find((h) => h.day_of_week === d);
        full.push(existing ?? { day_of_week: d, is_off: d === 6, start_time: "08:00", end_time: "18:00" });
      }
      setHours(full);
    }
  }, [wh.data]);

  const saveHours = useMutation({
    mutationFn: () => apiSend("put", "cleaner/working-hours", {
      hours: hours.map((h) => ({ day_of_week: h.day_of_week, is_off: h.is_off, start_time: h.is_off ? null : h.start_time, end_time: h.is_off ? null : h.end_time })),
    }),
    onSuccess: () => { toast.success("Working hours saved."); qc.invalidateQueries({ queryKey: ["cleaner", "working-hours"] }); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not save."),
  });

  const [off, setOff] = useState({ start_date: "", end_date: "", reason: "" });
  const addOff = useMutation({
    mutationFn: () => apiSend("post", "cleaner/time-off", off),
    onSuccess: () => { toast.success("Time off added."); setOff({ start_date: "", end_date: "", reason: "" }); qc.invalidateQueries({ queryKey: ["cleaner", "time-off"] }); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not add time off."),
  });
  const removeOff = useMutation({ mutationFn: (id: number) => apiSend("delete", `cleaner/time-off/${id}`), onSuccess: () => qc.invalidateQueries({ queryKey: ["cleaner", "time-off"] }) });

  const setDay = (i: number, patch: Partial<WorkingHour>) => setHours((prev) => prev.map((h, idx) => (idx === i ? { ...h, ...patch } : h)));

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold tracking-tight">Availability</h1>

      <Card className="rounded-2xl">
        <CardHeader><CardTitle className="text-base">Weekly working hours</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {wh.isLoading ? <LoadingRows rows={7} /> : hours.map((h, i) => (
            <div key={h.day_of_week} className="flex flex-wrap items-center gap-3">
              <span className="w-12 text-sm font-medium">{DAY_LABELS[h.day_of_week]}</span>
              <label className="flex items-center gap-2 text-sm text-muted-foreground">
                <Switch checked={!h.is_off} onCheckedChange={(v) => setDay(i, { is_off: !v })} /> {h.is_off ? "Off" : "Working"}
              </label>
              {!h.is_off ? (
                <div className="flex items-center gap-2">
                  <Input type="time" value={h.start_time ?? "08:00"} onChange={(e) => setDay(i, { start_time: e.target.value })} className="w-32" />
                  <span className="text-muted-foreground">to</span>
                  <Input type="time" value={h.end_time ?? "18:00"} onChange={(e) => setDay(i, { end_time: e.target.value })} className="w-32" />
                </div>
              ) : null}
            </div>
          ))}
          <Button onClick={() => saveHours.mutate()} disabled={saveHours.isPending}>Save working hours</Button>
        </CardContent>
      </Card>

      <Card className="rounded-2xl">
        <CardHeader><CardTitle className="text-base">Time off</CardTitle></CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-1"><label className="text-xs text-muted-foreground">From</label><Input type="date" value={off.start_date} onChange={(e) => setOff({ ...off, start_date: e.target.value })} className="w-40" /></div>
            <div className="space-y-1"><label className="text-xs text-muted-foreground">To</label><Input type="date" value={off.end_date} onChange={(e) => setOff({ ...off, end_date: e.target.value })} className="w-40" /></div>
            <div className="space-y-1"><label className="text-xs text-muted-foreground">Reason</label><Input value={off.reason} onChange={(e) => setOff({ ...off, reason: e.target.value })} className="w-48" placeholder="Optional" /></div>
            <Button onClick={() => addOff.mutate()} disabled={addOff.isPending || !off.start_date || !off.end_date}>Add</Button>
          </div>
          <div className="space-y-2">
            {timeOff.data?.length === 0 ? <p className="text-sm text-muted-foreground">No time off scheduled.</p> : timeOff.data?.map((t) => (
              <div key={t.id} className="flex items-center justify-between rounded-xl border px-3 py-2 text-sm">
                <span>{formatDate(t.start_date)} – {formatDate(t.end_date)}{t.reason ? ` · ${t.reason}` : ""}</span>
                <Button size="icon" variant="ghost" className="text-destructive" onClick={() => removeOff.mutate(t.id)}><Trash2 className="h-4 w-4" /></Button>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
