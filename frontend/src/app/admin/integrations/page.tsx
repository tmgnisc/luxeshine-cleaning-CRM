"use client";

import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { PageHeader } from "@/components/shared/page-header";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { CalendarFeed, CalendarFeedSource } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarCheck, PlugZap, Plus, RefreshCw, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

const SOURCES: { value: CalendarFeedSource; label: string }[] = [
  { value: "airbnb", label: "Airbnb" },
  { value: "booking_com", label: "Booking.com" },
  { value: "vrbo", label: "VRBO" },
  { value: "google", label: "Google Calendar" },
  { value: "ical", label: "Generic iCal" },
  { value: "other", label: "Other" },
];

type FeedForm = { id?: number; name: string; source: CalendarFeedSource; url: string; is_active: boolean };

const EMPTY: FeedForm = { name: "", source: "airbnb", url: "", is_active: true };

export default function AdminIntegrationsPage() {
  const qc = useQueryClient();
  const [form, setForm] = useState<FeedForm | null>(null);
  const [deleteFeed, setDeleteFeed] = useState<CalendarFeed | null>(null);

  const feeds = useQuery({ queryKey: ["admin", "calendar-feeds"], queryFn: () => apiGet<CalendarFeed[]>("admin/calendar-feeds") });
  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["admin", "calendar-feeds"] });
    qc.invalidateQueries({ queryKey: ["admin", "schedule"] });
  };

  const save = useMutation({
    mutationFn: (f: FeedForm) =>
      f.id
        ? apiSend("put", `admin/calendar-feeds/${f.id}`, f)
        : apiSend("post", "admin/calendar-feeds", f),
    onSuccess: () => { toast.success("Calendar feed saved."); setForm(null); invalidate(); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not save feed."),
  });

  const sync = useMutation({
    mutationFn: (id: number) => apiSend("post", `admin/calendar-feeds/${id}/sync`),
    onSuccess: () => { toast.success("Feed synced."); invalidate(); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Sync failed. Check the feed URL."),
  });

  const syncAll = useMutation({
    mutationFn: () => apiSend<CalendarFeed[]>("post", "admin/calendar-feeds/sync-all"),
    onSuccess: () => { toast.success("All feeds synced."); invalidate(); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Sync failed."),
  });

  const toggleActive = useMutation({
    mutationFn: ({ id, value }: { id: number; value: boolean }) => apiSend("patch", `admin/calendar-feeds/${id}`, { is_active: value }),
    onSuccess: () => invalidate(),
  });

  const remove = useMutation({
    mutationFn: (id: number) => apiSend("delete", `admin/calendar-feeds/${id}`),
    onSuccess: () => { toast.success("Feed removed."); setDeleteFeed(null); invalidate(); },
  });

  const rows = feeds.data ?? [];

  return (
    <div className="space-y-5">
      <PageHeader
        title="Integrations"
        subtitle="Connect Airbnb and other iCal calendars. Reservations appear read-only on the Schedule."
        actions={
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={() => syncAll.mutate()} disabled={syncAll.isPending || rows.length === 0}>
              <RefreshCw className={syncAll.isPending ? "h-4 w-4 animate-spin" : "h-4 w-4"} /> Sync all
            </Button>
            <Button onClick={() => setForm({ ...EMPTY })}><Plus className="h-4 w-4" /> Add feed</Button>
          </div>
        }
      />

      <div className="rounded-2xl border border-dashed bg-muted/30 p-4 text-sm text-muted-foreground">
        <p className="flex items-center gap-2 font-medium text-foreground"><PlugZap className="h-4 w-4" /> How it works</p>
        <p className="mt-1">
          Paste a calendar export URL (Airbnb → Listing → Availability → Sync calendars → Export). Feeds are pulled
          automatically every 30 minutes and whenever you press sync. API keys live in the URL token, so rotating a key is just editing the URL here — no redeploy needed.
        </p>
      </div>

      {feeds.isLoading ? (
        <LoadingRows />
      ) : rows.length === 0 ? (
        <EmptyState
          title="No calendar feeds yet"
          description="Add your first Airbnb or iCal feed to see reservations on the schedule."
          icon={CalendarCheck}
          action={<Button onClick={() => setForm({ ...EMPTY })}>Add feed</Button>}
        />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Source</TableHead>
                <TableHead>Last sync</TableHead>
                <TableHead>Reservations</TableHead>
                <TableHead>Active</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((f) => (
                <TableRow key={f.id}>
                  <TableCell className="font-medium">{f.name}</TableCell>
                  <TableCell><Badge variant="secondary">{SOURCES.find((s) => s.value === f.source)?.label ?? f.source}</Badge></TableCell>
                  <TableCell className="text-sm">
                    {f.last_sync_status === "success" ? (
                      <span className="text-green-600">Synced {formatDate(f.last_synced_at)}</span>
                    ) : f.last_sync_status === "failed" ? (
                      <span className="text-destructive" title={f.last_sync_message ?? undefined}>Failed</span>
                    ) : (
                      <span className="text-muted-foreground">Never</span>
                    )}
                    {f.last_sync_message ? <span className="block text-xs text-muted-foreground">{f.last_sync_message}</span> : null}
                  </TableCell>
                  <TableCell>{f.events_count}</TableCell>
                  <TableCell><Switch checked={f.is_active} onCheckedChange={(v) => toggleActive.mutate({ id: f.id, value: v })} /></TableCell>
                  <TableCell>
                    <div className="flex justify-end gap-1">
                      <Button size="icon" variant="ghost" title="Sync now" onClick={() => sync.mutate(f.id)} disabled={sync.isPending}>
                        <RefreshCw className="h-4 w-4" />
                      </Button>
                      <Button size="sm" variant="outline" onClick={() => setForm({ id: f.id, name: f.name, source: f.source, url: f.url, is_active: f.is_active })}>
                        Edit
                      </Button>
                      <Button size="icon" variant="ghost" className="text-destructive" title="Delete" onClick={() => setDeleteFeed(f)}>
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

      {/* Add / edit dialog */}
      <Dialog open={form !== null} onOpenChange={(o) => !o && setForm(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{form?.id ? "Edit feed" : "Add calendar feed"}</DialogTitle></DialogHeader>
          {form ? (
            <div className="space-y-4">
              <div className="space-y-1.5">
                <Label>Name</Label>
                <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Beach House (Airbnb)" />
              </div>
              <div className="space-y-1.5">
                <Label>Source</Label>
                <Select value={form.source} onValueChange={(v) => setForm({ ...form, source: v as CalendarFeedSource })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {SOURCES.map((s) => <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>iCal URL</Label>
                <Input value={form.url} onChange={(e) => setForm({ ...form, url: e.target.value })} placeholder="https://www.airbnb.com/calendar/ical/….ics?t=…" />
              </div>
              <label className="flex items-center justify-between rounded-xl border px-4 py-3">
                <span className="text-sm font-medium">Active (sync automatically)</span>
                <Switch checked={form.is_active} onCheckedChange={(v) => setForm({ ...form, is_active: v })} />
              </label>
            </div>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={() => setForm(null)}>Cancel</Button>
            <Button onClick={() => form && save.mutate(form)} disabled={save.isPending || !form?.name || !form?.url}>
              {save.isPending ? "Saving…" : "Save feed"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={deleteFeed !== null}
        onOpenChange={(o) => !o && setDeleteFeed(null)}
        title={`Remove ${deleteFeed?.name}?`}
        description="This deletes the feed and its imported reservations from the schedule. The external calendar itself is not affected."
        confirmLabel="Remove"
        destructive
        loading={remove.isPending}
        onConfirm={() => deleteFeed && remove.mutate(deleteFeed.id)}
      />
    </div>
  );
}
