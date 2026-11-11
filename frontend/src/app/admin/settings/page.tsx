"use client";

import { PageHeader } from "@/components/shared/page-header";
import { LoadingRows } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import { cn } from "@/lib/utils";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

type SettingsMap = Record<string, unknown>;

export default function AdminSettingsPage() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ["admin", "settings"], queryFn: () => apiGet<SettingsMap>("admin/settings") });
  const [form, setForm] = useState<SettingsMap>({});
  const [areaInput, setAreaInput] = useState("");

  useEffect(() => { if (data) setForm(data); }, [data]);

  const dirty = useMemo(() => data && JSON.stringify(form) !== JSON.stringify(data), [form, data]);

  const save = useMutation({
    mutationFn: () => apiSend("put", "admin/settings", form),
    onSuccess: () => { toast.success("Settings saved."); qc.invalidateQueries({ queryKey: ["admin", "settings"] }); qc.invalidateQueries({ queryKey: ["public", "settings"] }); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not save settings."),
  });

  const set = (k: string, v: unknown) => setForm((f) => ({ ...f, [k]: v }));
  const str = (k: string) => (form[k] as string) ?? "";
  const num = (k: string) => (form[k] as number) ?? 0;
  const bool = (k: string) => Boolean(form[k]);
  const areas = (form.service_areas as string[]) ?? [];

  if (isLoading) return <div className="space-y-4"><PageHeader title="Content & Settings" /><LoadingRows /></div>;

  return (
    <div className="space-y-6 pb-24">
      <PageHeader title="Content & Settings" subtitle="Manage your website content and operational rules." />

      <Section title="Website content">
        <Field label="Site title"><Input value={str("site_title")} onChange={(e) => set("site_title", e.target.value)} /></Field>
        <Field label="Tagline"><Input value={str("tagline")} onChange={(e) => set("tagline", e.target.value)} /></Field>
        <Field label="Hero headline"><Input value={str("hero_headline")} onChange={(e) => set("hero_headline", e.target.value)} /></Field>
        <Field label="Hero subline"><Textarea value={str("hero_subline")} onChange={(e) => set("hero_subline", e.target.value)} rows={2} /></Field>
      </Section>

      <Section title="Contact details">
        <Field label="Support phone"><Input value={str("support_phone")} onChange={(e) => set("support_phone", e.target.value)} /></Field>
        <Field label="Support email"><Input value={str("support_email")} onChange={(e) => set("support_email", e.target.value)} /></Field>
        <Field label="Office address"><Input value={str("office_address")} onChange={(e) => set("office_address", e.target.value)} /></Field>
      </Section>

      <Section title="Operations">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Currency"><Input value={str("currency")} onChange={(e) => set("currency", e.target.value.toUpperCase())} maxLength={3} /></Field>
          <div />
          <Field label="Booking lead time (hours)"><Input type="number" value={num("booking_lead_time_hours")} onChange={(e) => set("booking_lead_time_hours", Number(e.target.value))} /></Field>
          <Field label="Cancellation cutoff (hours)"><Input type="number" value={num("cancellation_cutoff_hours")} onChange={(e) => set("cancellation_cutoff_hours", Number(e.target.value))} /></Field>
          <Field label="Business open time"><Input type="time" value={str("business_open_time")} onChange={(e) => set("business_open_time", e.target.value)} /></Field>
          <Field label="Business close time"><Input type="time" value={str("business_close_time")} onChange={(e) => set("business_close_time", e.target.value)} /></Field>
        </div>

        <Field label="Service areas">
          <div className="flex flex-wrap gap-2">
            {areas.map((a) => (
              <span key={a} className="flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs text-primary">
                {a}
                <button onClick={() => set("service_areas", areas.filter((x) => x !== a))}><X className="h-3 w-3" /></button>
              </span>
            ))}
          </div>
          <div className="mt-2 flex gap-2">
            <Input
              value={areaInput}
              onChange={(e) => setAreaInput(e.target.value)}
              placeholder="Add an area…"
              onKeyDown={(e) => {
                if (e.key === "Enter" && areaInput.trim()) {
                  e.preventDefault();
                  set("service_areas", [...areas, areaInput.trim()]);
                  setAreaInput("");
                }
              }}
            />
          </div>
        </Field>

        <div className="space-y-3 pt-2">
          <label className="flex items-center justify-between rounded-xl border px-4 py-3">
            <span className="text-sm font-medium">Accepting new bookings</span>
            <Switch checked={bool("accepting_bookings")} onCheckedChange={(v) => set("accepting_bookings", v)} />
          </label>
          <label className="flex items-center justify-between rounded-xl border px-4 py-3">
            <span className="text-sm font-medium">Maintenance mode</span>
            <Switch checked={bool("maintenance_mode")} onCheckedChange={(v) => set("maintenance_mode", v)} />
          </label>
          {bool("maintenance_mode") ? (
            <Field label="Maintenance message"><Textarea value={str("maintenance_message")} onChange={(e) => set("maintenance_message", e.target.value)} rows={2} /></Field>
          ) : null}
        </div>
      </Section>

      {/* Sticky save bar */}
      <div className={cn(
        "fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 backdrop-blur transition-transform lg:pl-64",
        dirty ? "translate-y-0" : "translate-y-full",
      )}>
        <div className="mx-auto flex max-w-7xl items-center justify-end gap-3 px-6 py-3">
          <span className="mr-auto text-sm text-muted-foreground">You have unsaved changes.</span>
          <Button variant="outline" onClick={() => data && setForm(data)}>Discard</Button>
          <Button onClick={() => save.mutate()} disabled={save.isPending}>{save.isPending ? "Saving…" : "Save changes"}</Button>
        </div>
      </div>
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Card className="rounded-2xl">
      <CardHeader><CardTitle className="text-base">{title}</CardTitle></CardHeader>
      <CardContent className="space-y-4">{children}</CardContent>
    </Card>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <div className="space-y-1.5"><Label>{label}</Label>{children}</div>;
}
