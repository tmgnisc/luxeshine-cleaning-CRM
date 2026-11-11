"use client";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useCurrency } from "@/hooks/use-settings";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import type { Address, Customer, Service } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

interface EligibleCleaner {
  id: number;
  name: string;
  rating_avg: number;
  eligible: boolean;
  reasons: string[];
}

export function BookingFormDialog({
  open,
  onOpenChange,
  initialDate,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  initialDate?: string;
}) {
  const qc = useQueryClient();
  const currency = useCurrency();

  const [customerId, setCustomerId] = useState<string>("");
  const [serviceId, setServiceId] = useState<string>("");
  const [addressId, setAddressId] = useState<string>("");
  const [date, setDate] = useState("");
  const [startTime, setStartTime] = useState("10:00");
  const [duration, setDuration] = useState("2");
  const [cleanerId, setCleanerId] = useState<string>("none");
  const [notes, setNotes] = useState("");

  const customers = useQuery({
    queryKey: ["admin", "customers", "all"],
    queryFn: () => apiGet<Customer[]>("admin/customers", { params: { per_page: 100 } }),
    enabled: open,
  });
  const services = useQuery({
    queryKey: ["admin", "services", "all"],
    queryFn: () => apiGet<Service[]>("admin/services", { params: { per_page: 100 } }),
    enabled: open,
  });
  const addresses = useQuery({
    queryKey: ["admin", "customer-addresses", customerId],
    queryFn: () => apiGet<Address[]>(`admin/customers/${customerId}/addresses`),
    enabled: open && !!customerId,
  });

  const service = useMemo(
    () => services.data?.find((s) => String(s.id) === serviceId),
    [services.data, serviceId],
  );
  const price = service ? service.price_per_hour * Number(duration || 0) : 0;

  const eligible = useQuery({
    queryKey: ["admin", "available-cleaners", serviceId, date, startTime, duration],
    queryFn: () =>
      apiGet<EligibleCleaner[]>("admin/available-cleaners", {
        params: { service_id: serviceId, date, start_time: startTime, duration, show_all: true },
      }),
    enabled: open && !!serviceId && !!date && !!startTime && !!duration,
  });

  useEffect(() => {
    if (service) setDuration(String(service.default_duration_hours));
  }, [service]);

  useEffect(() => {
    if (open) setDate(initialDate ?? "");
  }, [open, initialDate]);

  const create = useMutation({
    mutationFn: () =>
      apiSend("post", "admin/bookings", {
        customer_id: Number(customerId),
        service_id: Number(serviceId),
        address_id: addressId ? Number(addressId) : null,
        cleaner_id: cleanerId !== "none" ? Number(cleanerId) : null,
        scheduled_date: date,
        start_time: startTime,
        duration_hours: Number(duration),
        customer_notes: notes || null,
      }),
    onSuccess: () => {
      toast.success("Booking created.");
      qc.invalidateQueries({ queryKey: ["admin", "bookings"] });
      onOpenChange(false);
      reset();
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not create booking."),
  });

  const reset = () => {
    setCustomerId("");
    setServiceId("");
    setAddressId("");
    setDate("");
    setCleanerId("none");
    setNotes("");
  };

  const canSubmit = customerId && serviceId && date && startTime && duration;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>New booking</DialogTitle>
          <DialogDescription>Create a booking on behalf of a customer.</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <Field label="Customer">
            <Select value={customerId} onValueChange={(v) => { setCustomerId(v); setAddressId(""); }}>
              <SelectTrigger><SelectValue placeholder="Select customer" /></SelectTrigger>
              <SelectContent>
                {customers.data?.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>{c.name} · {c.email}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <Field label="Service">
            <Select value={serviceId} onValueChange={setServiceId}>
              <SelectTrigger><SelectValue placeholder="Select service" /></SelectTrigger>
              <SelectContent>
                {services.data?.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>{s.name} · {currency(s.price_per_hour)}/h</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          {customerId ? (
            <Field label="Address">
              <Select value={addressId} onValueChange={setAddressId}>
                <SelectTrigger><SelectValue placeholder="Select address" /></SelectTrigger>
                <SelectContent>
                  {addresses.data?.map((a) => (
                    <SelectItem key={a.id} value={String(a.id)}>{a.label} — {a.area}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          ) : null}

          <div className="grid grid-cols-3 gap-3">
            <Field label="Date">
              <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
            </Field>
            <Field label="Start">
              <Input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} />
            </Field>
            <Field label="Hours">
              <Input type="number" step="0.5" min="0.5" value={duration} onChange={(e) => setDuration(e.target.value)} />
            </Field>
          </div>

          {service ? (
            <div className="rounded-xl bg-primary/5 px-3 py-2 text-sm">
              Estimated total: <span className="font-semibold">{currency(price)}</span>
            </div>
          ) : null}

          <Field label="Cleaner">
            <Select value={cleanerId} onValueChange={setCleanerId}>
              <SelectTrigger><SelectValue placeholder="Unassigned" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="none">Leave unassigned</SelectItem>
                {eligible.data?.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)} disabled={!c.eligible}>
                    {c.name} {c.eligible ? `· ★ ${c.rating_avg}` : `· ${c.reasons[0] ?? "ineligible"}`}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <Field label="Customer notes">
            <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Access, pets, parking…" />
          </Field>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button disabled={!canSubmit || create.isPending} onClick={() => create.mutate()}>
            {create.isPending ? "Creating…" : "Create booking"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      {children}
    </div>
  );
}
