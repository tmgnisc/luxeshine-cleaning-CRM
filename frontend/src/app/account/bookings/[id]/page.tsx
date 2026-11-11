"use client";

import { StarRating } from "@/components/shared/star-rating";
import { BookingStatusBadge } from "@/components/shared/status-badge";
import { LoadingCards } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { useCurrency } from "@/hooks/use-settings";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import { formatDate, formatDateTime, formatTime } from "@/lib/format";
import type { AvailabilitySlot, Booking } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import { use, useState } from "react";
import { toast } from "sonner";

export default function BookingDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const qc = useQueryClient();
  const currency = useCurrency();
  const [showReschedule, setShowReschedule] = useState(false);
  const [showCancel, setShowCancel] = useState(false);

  const { data: b, isLoading } = useQuery({ queryKey: ["customer", "booking", id], queryFn: () => apiGet<Booking>(`customer/bookings/${id}`) });

  const invalidate = () => { qc.invalidateQueries({ queryKey: ["customer", "booking", id] }); qc.invalidateQueries({ queryKey: ["customer", "bookings"] }); };

  if (isLoading || !b) return <div className="space-y-4"><LoadingCards count={1} /></div>;

  const canModify = b.status === "pending" || b.status === "confirmed";
  const canReview = b.status === "completed" && !b.review;

  return (
    <div className="space-y-6">
      <Button asChild variant="ghost" size="sm" className="-ml-2"><Link href="/account/bookings"><ArrowLeft className="h-4 w-4" /> Back to bookings</Link></Button>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-semibold">{b.service?.name}</h1>
            <BookingStatusBadge status={b.status} />
          </div>
          <p className="text-sm text-muted-foreground">{b.reference} · {formatDate(b.scheduled_date)} at {formatTime(b.start_time)}</p>
        </div>
        {canModify ? (
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setShowReschedule(true)}>Reschedule</Button>
            <Button variant="outline" className="text-destructive" onClick={() => setShowCancel(true)}>Cancel</Button>
          </div>
        ) : null}
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="rounded-2xl lg:col-span-2">
          <CardHeader><CardTitle className="text-base">Booking details</CardTitle></CardHeader>
          <CardContent className="space-y-2 text-sm">
            <Row label="Duration" value={`${b.duration_hours} hours`} />
            <Row label="Cleaner" value={b.cleaner?.name ?? "To be assigned"} />
            <Row label="Address" value={b.address_snapshot ? `${b.address_snapshot.line1}, ${b.address_snapshot.area}` : "—"} />
            <Row label="Total" value={currency(b.total_price)} />
            <Row label="Payment" value={b.is_paid ? "Paid" : "Payable on completion"} />
            {b.customer_notes ? <Row label="Notes" value={b.customer_notes} /> : null}
          </CardContent>
        </Card>

        <Card className="rounded-2xl">
          <CardHeader><CardTitle className="text-base">Status timeline</CardTitle></CardHeader>
          <CardContent>
            <ol className="space-y-3 border-l-2 border-border pl-4 text-sm">
              {b.status_history?.map((h) => (
                <li key={h.id} className="relative">
                  <span className="absolute -left-[22px] top-1 h-2.5 w-2.5 rounded-full bg-primary" />
                  <p className="font-medium capitalize">{h.to_status.replace("_", " ")}</p>
                  <p className="text-xs text-muted-foreground">{formatDateTime(h.created_at)}</p>
                </li>
              ))}
            </ol>
          </CardContent>
        </Card>
      </div>

      {canReview ? <ReviewCard bookingId={b.id} onDone={invalidate} /> : null}
      {b.review ? (
        <Card className="rounded-2xl">
          <CardHeader><CardTitle className="text-base">Your review</CardTitle></CardHeader>
          <CardContent><StarRating value={b.review.rating} /><p className="mt-2 text-sm text-muted-foreground">{b.review.comment}</p></CardContent>
        </Card>
      ) : null}

      {b && <RescheduleDialog booking={b} open={showReschedule} onOpenChange={setShowReschedule} onDone={invalidate} />}
      <CancelDialog bookingId={b.id} open={showCancel} onOpenChange={setShowCancel} onDone={invalidate} />
    </div>
  );
}

function Row({ label, value }: { label: string; value?: string }) {
  return <div className="flex justify-between gap-4"><span className="text-muted-foreground">{label}</span><span className="text-right font-medium">{value ?? "—"}</span></div>;
}

function ReviewCard({ bookingId, onDone }: { bookingId: number; onDone: () => void }) {
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState("");
  const submit = useMutation({
    mutationFn: () => apiSend("post", `customer/bookings/${bookingId}/review`, { rating, comment }),
    onSuccess: () => { toast.success("Thanks for your review!"); onDone(); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not submit review."),
  });
  return (
    <Card className="rounded-2xl border-primary/30">
      <CardHeader><CardTitle className="text-base">Leave a review</CardTitle></CardHeader>
      <CardContent className="space-y-3">
        <StarRating value={rating} onChange={setRating} size={24} />
        <Textarea rows={3} value={comment} onChange={(e) => setComment(e.target.value)} placeholder="How was your cleaning?" />
        <Button onClick={() => submit.mutate()} disabled={submit.isPending || comment.length < 3}>Submit review</Button>
      </CardContent>
    </Card>
  );
}

function RescheduleDialog({ booking, open, onOpenChange, onDone }: { booking: Booking; open: boolean; onOpenChange: (o: boolean) => void; onDone: () => void }) {
  const [date, setDate] = useState(booking.scheduled_date);
  const [time, setTime] = useState(booking.start_time);
  const availability = useQuery({
    queryKey: ["public", "availability", booking.service_id, date, booking.duration_hours],
    queryFn: () => apiGet<{ slots: AvailabilitySlot[] }>("public/availability", { params: { service_id: booking.service_id, date, duration: booking.duration_hours } }),
    enabled: open && !!date,
  });
  const submit = useMutation({
    mutationFn: () => apiSend("put", `customer/bookings/${booking.id}/reschedule`, { scheduled_date: date, start_time: time, duration_hours: booking.duration_hours }),
    onSuccess: () => { toast.success("Booking rescheduled."); onDone(); onOpenChange(false); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not reschedule."),
  });
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader><DialogTitle>Reschedule booking</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div className="space-y-1.5"><Label>Date</Label><Input type="date" min={new Date().toISOString().slice(0, 10)} value={date} onChange={(e) => { setDate(e.target.value); setTime(""); }} /></div>
          <div>
            <Label>Time</Label>
            <div className="mt-2 grid grid-cols-4 gap-2">
              {availability.data?.slots.map((s) => (
                <button key={s.start_time} onClick={() => setTime(s.start_time)}
                  className={`rounded-lg border py-2 text-sm ${time === s.start_time ? "border-primary bg-primary text-primary-foreground" : "hover:bg-muted"}`}>
                  {formatTime(s.start_time)}
                </button>
              ))}
            </div>
            {availability.data && availability.data.slots.length === 0 ? <p className="mt-2 text-sm text-muted-foreground">No slots on this day.</p> : null}
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button onClick={() => submit.mutate()} disabled={submit.isPending || !date || !time}>Confirm</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function CancelDialog({ bookingId, open, onOpenChange, onDone }: { bookingId: number; open: boolean; onOpenChange: (o: boolean) => void; onDone: () => void }) {
  const [reason, setReason] = useState("");
  const submit = useMutation({
    mutationFn: () => apiSend("post", `customer/bookings/${bookingId}/cancel`, { reason }),
    onSuccess: () => { toast.success("Booking cancelled."); onDone(); onOpenChange(false); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not cancel."),
  });
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader><DialogTitle>Cancel booking</DialogTitle></DialogHeader>
        <div className="space-y-2">
          <Label>Reason (optional)</Label>
          <Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Let us know why…" />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Keep booking</Button>
          <Button className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => submit.mutate()} disabled={submit.isPending}>Cancel booking</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
