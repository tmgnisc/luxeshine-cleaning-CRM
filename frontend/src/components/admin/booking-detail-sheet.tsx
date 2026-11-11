"use client";

import { BookingStatusBadge } from "@/components/shared/status-badge";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { useCurrency } from "@/hooks/use-settings";
import { apiGet, apiSend } from "@/lib/api";
import { formatDate, formatDateTime, formatTime } from "@/lib/format";
import type { Booking } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

export function BookingDetailSheet({
  bookingId,
  open,
  onOpenChange,
}: {
  bookingId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const qc = useQueryClient();
  const currency = useCurrency();

  const { data: booking } = useQuery({
    queryKey: ["admin", "booking", bookingId],
    queryFn: () => apiGet<Booking>(`admin/bookings/${bookingId}`),
    enabled: open && !!bookingId,
  });

  const paid = useMutation({
    mutationFn: () => apiSend("patch", `admin/bookings/${bookingId}/paid`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["admin", "booking", bookingId] });
      qc.invalidateQueries({ queryKey: ["admin", "bookings"] });
      toast.success("Payment status updated.");
    },
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            {booking?.reference ?? "Booking"}
            {booking ? <BookingStatusBadge status={booking.status} /> : null}
          </SheetTitle>
          <SheetDescription>
            {booking ? `${formatDate(booking.scheduled_date)} · ${formatTime(booking.start_time)}–${formatTime(booking.end_time)}` : ""}
          </SheetDescription>
        </SheetHeader>

        {booking ? (
          <div className="space-y-5 py-4 text-sm">
            <Row label="Customer" value={booking.customer?.name} sub={booking.customer?.phone} />
            <Row label="Service" value={booking.service?.name} />
            <Row label="Cleaner" value={booking.cleaner?.name ?? "Unassigned"} />
            <Row
              label="Address"
              value={booking.address_snapshot ? `${booking.address_snapshot.line1}, ${booking.address_snapshot.area}` : "—"}
            />
            <Row label="Duration" value={`${booking.duration_hours} h`} />
            <Row
              label="Total"
              value={`${currency(booking.total_price)}${booking.price_overridden ? " (overridden)" : ""}`}
            />
            <div className="flex items-center justify-between rounded-xl bg-muted/50 px-3 py-2">
              <span className="text-muted-foreground">Payment</span>
              <Button size="sm" variant={booking.is_paid ? "default" : "outline"} onClick={() => paid.mutate()}>
                {booking.is_paid ? "Paid" : "Mark paid"}
              </Button>
            </div>

            {booking.customer_notes ? (
              <div>
                <p className="mb-1 font-medium">Customer notes</p>
                <p className="rounded-xl bg-muted/40 p-3 text-muted-foreground">{booking.customer_notes}</p>
              </div>
            ) : null}

            <div>
              <p className="mb-2 font-medium">Status timeline</p>
              <ol className="space-y-2 border-l-2 border-border pl-4">
                {booking.status_history?.map((h) => (
                  <li key={h.id} className="relative">
                    <span className="absolute -left-[22px] top-1 h-2.5 w-2.5 rounded-full bg-primary" />
                    <p className="text-sm">
                      {h.from_status ? `${h.from_status} → ` : ""}
                      <span className="font-medium">{h.to_status}</span>
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {formatDateTime(h.created_at)} {h.changed_by ? `· ${h.changed_by}` : ""}
                    </p>
                  </li>
                ))}
              </ol>
            </div>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}

function Row({ label, value, sub }: { label: string; value?: string | null; sub?: string | null }) {
  return (
    <div className="flex items-start justify-between gap-4">
      <span className="text-muted-foreground">{label}</span>
      <span className="text-right font-medium">
        {value ?? "—"}
        {sub ? <span className="block text-xs font-normal text-muted-foreground">{sub}</span> : null}
      </span>
    </div>
  );
}
