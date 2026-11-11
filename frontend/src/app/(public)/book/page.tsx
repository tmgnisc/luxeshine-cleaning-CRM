"use client";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { usePublicSettings } from "@/hooks/use-settings";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import { formatCurrency, formatDate, formatTime } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { Address, AvailabilitySlot, Booking, Service } from "@/lib/types";
import { useAuth } from "@/providers/auth-provider";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, Loader2 } from "lucide-react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

const STEPS = ["Service", "Duration", "Date & time", "Address", "Notes", "Confirm"];
const STORE_KEY = "dtd_booking_wizard";

function Wizard() {
  const params = useSearchParams();
  const { user } = useAuth();
  const { data: settings } = usePublicSettings();
  const qc = useQueryClient();
  const currency = settings?.currency ?? "NZD";

  const [step, setStep] = useState(0);
  const [serviceId, setServiceId] = useState<number | null>(null);
  const [duration, setDuration] = useState(2);
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [addressId, setAddressId] = useState<number | null>(null);
  const [notes, setNotes] = useState("");
  const [confirmed, setConfirmed] = useState<Booking | null>(null);

  const services = useQuery({ queryKey: ["public", "services"], queryFn: () => apiGet<Service[]>("public/services") });
  const service = useMemo(() => services.data?.find((s) => s.id === serviceId), [services.data, serviceId]);

  // Restore persisted wizard state (e.g. after signing in).
  useEffect(() => {
    try {
      const raw = sessionStorage.getItem(STORE_KEY);
      if (raw) {
        const s = JSON.parse(raw);
        if (s.serviceId) setServiceId(s.serviceId);
        if (s.duration) setDuration(s.duration);
        if (s.date) setDate(s.date);
        if (s.time) setTime(s.time);
        if (s.notes) setNotes(s.notes);
        if (typeof s.step === "number") setStep(s.step);
      }
    } catch {}
  }, []);

  // Preselect service from ?service=slug.
  useEffect(() => {
    const slug = params.get("service");
    if (slug && services.data && !serviceId) {
      const match = services.data.find((s) => s.slug === slug);
      if (match) { setServiceId(match.id); setDuration(match.default_duration_hours); }
    }
  }, [params, services.data, serviceId]);

  useEffect(() => {
    sessionStorage.setItem(STORE_KEY, JSON.stringify({ serviceId, duration, date, time, notes, step }));
  }, [serviceId, duration, date, time, notes, step]);

  const availability = useQuery({
    queryKey: ["public", "availability", serviceId, date, duration],
    queryFn: () => apiGet<{ slots: AvailabilitySlot[] }>("public/availability", { params: { service_id: serviceId, date, duration } }),
    enabled: !!serviceId && !!date && step === 2,
  });

  const addresses = useQuery({
    queryKey: ["customer", "addresses"],
    queryFn: () => apiGet<Address[]>("customer/addresses"),
    enabled: !!user && user.role === "customer" && step === 3,
  });

  const create = useMutation({
    mutationFn: () =>
      apiSend<Booking>("post", "customer/bookings", {
        service_id: serviceId, address_id: addressId, scheduled_date: date,
        start_time: time, duration_hours: duration, customer_notes: notes || null,
      }),
    onSuccess: (booking) => {
      setConfirmed(booking);
      sessionStorage.removeItem(STORE_KEY);
      qc.invalidateQueries({ queryKey: ["customer", "bookings"] });
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not place booking."),
  });

  const price = service ? service.price_per_hour * duration : 0;
  const today = new Date().toISOString().slice(0, 10);

  if (confirmed) {
    return (
      <div className="mx-auto max-w-lg px-4 py-20 text-center">
        <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary"><Check className="h-8 w-8" /></div>
        <h1 className="text-2xl font-semibold">Booking confirmed!</h1>
        <p className="mt-2 text-muted-foreground">Your reference is <span className="font-semibold text-foreground">{confirmed.reference}</span>.</p>
        <Card className="mt-6 rounded-2xl text-left"><CardContent className="space-y-2 p-5 text-sm">
          <Row label="Service" value={service?.name} />
          <Row label="When" value={`${formatDate(date)} at ${formatTime(time)}`} />
          <Row label="Duration" value={`${duration} hours`} />
          <Row label="Total" value={formatCurrency(price, currency)} />
        </CardContent></Card>
        <div className="mt-6 flex justify-center gap-3">
          <Button asChild><Link href="/account/bookings">View my bookings</Link></Button>
          <Button asChild variant="outline"><Link href="/">Back home</Link></Button>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-semibold tracking-tight">Book a cleaning</h1>

      {/* Progress */}
      <div className="mt-6 flex items-center gap-2">
        {STEPS.map((label, i) => (
          <div key={label} className="flex flex-1 items-center gap-2">
            <div className={cn("flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold", i <= step ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground")}>
              {i < step ? <Check className="h-3.5 w-3.5" /> : i + 1}
            </div>
            {i < STEPS.length - 1 ? <div className={cn("h-0.5 flex-1 rounded", i < step ? "bg-primary" : "bg-muted")} /> : null}
          </div>
        ))}
      </div>
      <p className="mt-3 text-sm font-medium text-muted-foreground">Step {step + 1} of {STEPS.length}: {STEPS[step]}</p>

      <Card className="mt-5 rounded-2xl">
        <CardContent className="p-6">
          {/* Step 1: service */}
          {step === 0 && (
            <div className="space-y-3">
              {services.data?.map((s) => (
                <button key={s.id} onClick={() => { setServiceId(s.id); setDuration(s.default_duration_hours); }}
                  className={cn("flex w-full items-center justify-between rounded-xl border p-4 text-left transition-colors", serviceId === s.id ? "border-primary bg-primary/5" : "hover:bg-muted/50")}>
                  <div><p className="font-medium">{s.name}</p><p className="text-sm text-muted-foreground">{s.short_description}</p></div>
                  <span className="font-semibold text-primary">{formatCurrency(s.price_per_hour, currency)}/h</span>
                </button>
              ))}
            </div>
          )}

          {/* Step 2: duration */}
          {step === 1 && service && (
            <div className="space-y-4">
              <Label>How many hours do you need?</Label>
              <div className="flex flex-wrap gap-2">
                {durationOptions(service).map((h) => (
                  <button key={h} onClick={() => setDuration(h)}
                    className={cn("rounded-xl border px-4 py-2 text-sm font-medium", duration === h ? "border-primary bg-primary text-primary-foreground" : "hover:bg-muted")}>
                    {h}h
                  </button>
                ))}
              </div>
              <div className="rounded-xl bg-primary/5 p-4 text-center">
                <p className="text-sm text-muted-foreground">Estimated price</p>
                <p className="text-2xl font-semibold text-primary">{formatCurrency(price, currency)}</p>
              </div>
            </div>
          )}

          {/* Step 3: date & time */}
          {step === 2 && (
            <div className="space-y-4">
              <div className="space-y-1.5"><Label>Date</Label><Input type="date" min={today} value={date} onChange={(e) => { setDate(e.target.value); setTime(""); }} /></div>
              {date ? (
                <div>
                  <Label>Available times</Label>
                  {availability.isLoading ? (
                    <p className="mt-2 flex items-center gap-2 text-sm text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> Checking availability…</p>
                  ) : availability.data && availability.data.slots.length > 0 ? (
                    <div className="mt-2 grid grid-cols-4 gap-2">
                      {availability.data.slots.map((slot) => (
                        <button key={slot.start_time} onClick={() => setTime(slot.start_time)}
                          className={cn("rounded-lg border py-2 text-sm", time === slot.start_time ? "border-primary bg-primary text-primary-foreground" : "hover:bg-muted")}>
                          {formatTime(slot.start_time)}
                        </button>
                      ))}
                    </div>
                  ) : (
                    <p className="mt-2 text-sm text-muted-foreground">No availability on this day. Please pick another date.</p>
                  )}
                </div>
              ) : null}
            </div>
          )}

          {/* Step 4: address */}
          {step === 3 && (
            !user || user.role !== "customer" ? (
              <div className="space-y-4 text-center">
                <p className="text-sm text-muted-foreground">Please sign in or create an account to complete your booking. Your selection will be saved.</p>
                <div className="flex justify-center gap-3">
                  <Button asChild><Link href="/login?next=/book">Sign in</Link></Button>
                  <Button asChild variant="outline"><Link href="/register?next=/book">Create account</Link></Button>
                </div>
              </div>
            ) : (
              <AddressStep addresses={addresses.data ?? []} value={addressId} onChange={setAddressId} onAdded={() => addresses.refetch()} />
            )
          )}

          {/* Step 5: notes */}
          {step === 4 && (
            <div className="space-y-2">
              <Label>Anything we should know? (optional)</Label>
              <Textarea rows={4} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Access instructions, pets, parking…" />
            </div>
          )}

          {/* Step 6: confirm */}
          {step === 5 && (
            <div className="space-y-2 text-sm">
              <Row label="Service" value={service?.name} />
              <Row label="Date & time" value={`${formatDate(date)} at ${formatTime(time)}`} />
              <Row label="Duration" value={`${duration} hours`} />
              <Row label="Address" value={addresses.data?.find((a) => a.id === addressId)?.label ?? "Selected address"} />
              <div className="mt-3 flex items-center justify-between rounded-xl bg-primary/5 p-4">
                <span className="font-medium">Total</span><span className="text-xl font-semibold text-primary">{formatCurrency(price, currency)}</span>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Nav */}
      <div className="mt-5 flex justify-between">
        <Button variant="outline" disabled={step === 0} onClick={() => setStep((s) => s - 1)}>Back</Button>
        {step < 5 ? (
          <Button disabled={!canAdvance(step, { serviceId, date, time, user: !!user && user.role === "customer", addressId })} onClick={() => setStep((s) => s + 1)}>Continue</Button>
        ) : (
          <Button disabled={create.isPending || !addressId} onClick={() => create.mutate()}>
            {create.isPending ? "Booking…" : "Confirm booking"}
          </Button>
        )}
      </div>
    </div>
  );
}

function canAdvance(step: number, s: { serviceId: number | null; date: string; time: string; user: boolean; addressId: number | null }): boolean {
  if (step === 0) return !!s.serviceId;
  if (step === 2) return !!s.date && !!s.time;
  if (step === 3) return s.user && !!s.addressId;
  return true;
}

function durationOptions(service: Service): number[] {
  const out: number[] = [];
  for (let h = Math.ceil(service.min_duration_hours); h <= Math.floor(service.max_duration_hours); h++) out.push(h);
  return out;
}

function AddressStep({ addresses, value, onChange, onAdded }: { addresses: Address[]; value: number | null; onChange: (id: number) => void; onAdded: () => void }) {
  const [adding, setAdding] = useState(addresses.length === 0);
  const [form, setForm] = useState({ label: "Home", line1: "", area: "", city: "Dubai" });
  const save = useMutation({
    mutationFn: () => apiSend<Address>("post", "customer/addresses", form),
    onSuccess: (addr) => { toast.success("Address saved."); onAdded(); onChange(addr.id); setAdding(false); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not save address."),
  });

  return (
    <div className="space-y-3">
      {addresses.map((a) => (
        <button key={a.id} onClick={() => onChange(a.id)}
          className={cn("flex w-full flex-col rounded-xl border p-3 text-left text-sm", value === a.id ? "border-primary bg-primary/5" : "hover:bg-muted/50")}>
          <span className="font-medium">{a.label}</span>
          <span className="text-muted-foreground">{a.line1}, {a.area}, {a.city}</span>
        </button>
      ))}

      {adding ? (
        <div className="space-y-3 rounded-xl border border-dashed p-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5"><Label>Label</Label><Input value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} /></div>
            <div className="space-y-1.5"><Label>Area</Label><Input value={form.area} onChange={(e) => setForm({ ...form, area: e.target.value })} /></div>
          </div>
          <div className="space-y-1.5"><Label>Address line</Label><Input value={form.line1} onChange={(e) => setForm({ ...form, line1: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>City</Label><Input value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} /></div>
          <Button size="sm" onClick={() => save.mutate()} disabled={save.isPending || !form.line1 || !form.area}>Save address</Button>
        </div>
      ) : (
        <Button variant="outline" size="sm" onClick={() => setAdding(true)}>+ Add new address</Button>
      )}
    </div>
  );
}

function Row({ label, value }: { label: string; value?: string }) {
  return <div className="flex justify-between"><span className="text-muted-foreground">{label}</span><span className="font-medium">{value ?? "—"}</span></div>;
}

export default function BookPage() {
  return <Suspense><Wizard /></Suspense>;
}
