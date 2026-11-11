"use client";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import type { Booking } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, X } from "lucide-react";
import { toast } from "sonner";

interface EligibleCleaner {
  id: number;
  name: string;
  rating_avg: number;
  eligible: boolean;
  reasons: string[];
}

export function AssignCleanerDialog({
  booking,
  open,
  onOpenChange,
}: {
  booking: Booking | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const qc = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "assign-options", booking?.id],
    queryFn: () =>
      apiGet<EligibleCleaner[]>("admin/available-cleaners", {
        params: {
          service_id: booking!.service_id,
          date: booking!.scheduled_date,
          start_time: booking!.start_time,
          duration: booking!.duration_hours,
          exclude_booking_id: booking!.id,
          show_all: true,
        },
      }),
    enabled: open && !!booking,
  });

  const assign = useMutation({
    mutationFn: (cleanerId: number | null) =>
      apiSend("patch", `admin/bookings/${booking!.id}/assign`, { cleaner_id: cleanerId }),
    onSuccess: () => {
      toast.success("Assignment updated.");
      qc.invalidateQueries({ queryKey: ["admin", "bookings"] });
      onOpenChange(false);
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not assign cleaner."),
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Assign cleaner</DialogTitle>
          <DialogDescription>
            {booking ? `${booking.reference} · only eligible cleaners can be assigned.` : ""}
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-80 space-y-2 overflow-y-auto">
          {isLoading ? (
            <p className="py-6 text-center text-sm text-muted-foreground">Checking availability…</p>
          ) : (
            data?.map((c) => (
              <button
                key={c.id}
                disabled={!c.eligible || assign.isPending}
                onClick={() => assign.mutate(c.id)}
                className="flex w-full items-center justify-between gap-2 rounded-xl border px-3 py-2.5 text-left text-sm enabled:hover:border-primary enabled:hover:bg-primary/5 disabled:opacity-60"
                title={c.reasons.join(" ")}
              >
                <span className="font-medium">{c.name}</span>
                {c.eligible ? (
                  <span className="flex items-center gap-1 text-primary">
                    <Check className="h-4 w-4" /> ★ {c.rating_avg}
                  </span>
                ) : (
                  <span className="flex items-center gap-1 text-xs text-muted-foreground">
                    <X className="h-3.5 w-3.5" /> {c.reasons[0]}
                  </span>
                )}
              </button>
            ))
          )}
        </div>

        {booking?.cleaner_id ? (
          <Button variant="outline" onClick={() => assign.mutate(null)} disabled={assign.isPending}>
            Unassign current cleaner
          </Button>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}
