"use client";

import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import type { Address } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { MapPin, Pencil, Star, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

const EMPTY = { label: "Home", line1: "", line2: "", area: "", city: "Dubai", access_instructions: "" };

export default function AddressesPage() {
  const qc = useQueryClient();
  const [editing, setEditing] = useState<Address | null>(null);
  const [show, setShow] = useState(false);
  const [form, setForm] = useState(EMPTY);

  const { data, isLoading } = useQuery({ queryKey: ["customer", "addresses"], queryFn: () => apiGet<Address[]>("customer/addresses") });
  const invalidate = () => qc.invalidateQueries({ queryKey: ["customer", "addresses"] });

  const save = useMutation({
    mutationFn: () => (editing ? apiSend("put", `customer/addresses/${editing.id}`, form) : apiSend("post", "customer/addresses", form)),
    onSuccess: () => { toast.success("Address saved."); invalidate(); setShow(false); setEditing(null); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not save address."),
  });
  const remove = useMutation({ mutationFn: (id: number) => apiSend("delete", `customer/addresses/${id}`), onSuccess: () => { invalidate(); toast.success("Address removed."); } });
  const setDefault = useMutation({ mutationFn: (id: number) => apiSend("post", `customer/addresses/${id}/default`), onSuccess: invalidate });

  const openNew = () => { setEditing(null); setForm(EMPTY); setShow(true); };
  const openEdit = (a: Address) => { setEditing(a); setForm({ label: a.label, line1: a.line1, line2: a.line2 ?? "", area: a.area, city: a.city, access_instructions: a.access_instructions ?? "" }); setShow(true); };

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold tracking-tight">My addresses</h1>
        <Button onClick={openNew}><MapPin className="h-4 w-4" /> Add address</Button>
      </div>

      {isLoading ? (
        <LoadingRows rows={2} />
      ) : data && data.length === 0 ? (
        <EmptyState title="No addresses yet" description="Add an address to speed up booking." icon={MapPin} action={<Button onClick={openNew}>Add your first address</Button>} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {data?.map((a) => (
            <Card key={a.id} className="rounded-2xl">
              <CardContent className="p-5">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-2">
                    <p className="font-semibold">{a.label}</p>
                    {a.is_default ? <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">Default</span> : null}
                  </div>
                  <div className="flex gap-1">
                    <Button size="icon" variant="ghost" onClick={() => openEdit(a)}><Pencil className="h-4 w-4" /></Button>
                    <Button size="icon" variant="ghost" className="text-destructive" onClick={() => { if (confirm("Remove this address?")) remove.mutate(a.id); }}><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </div>
                <p className="mt-1 text-sm text-muted-foreground">{a.line1}{a.line2 ? `, ${a.line2}` : ""}, {a.area}, {a.city}</p>
                {!a.is_default ? <Button size="sm" variant="ghost" className="mt-3 text-primary" onClick={() => setDefault.mutate(a.id)}><Star className="h-3.5 w-3.5" /> Set as default</Button> : null}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={show} onOpenChange={setShow}>
        <DialogContent>
          <DialogHeader><DialogTitle>{editing ? "Edit address" : "Add address"}</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5"><Label>Label</Label><Input value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} /></div>
              <div className="space-y-1.5"><Label>Area</Label><Input value={form.area} onChange={(e) => setForm({ ...form, area: e.target.value })} /></div>
            </div>
            <div className="space-y-1.5"><Label>Address line 1</Label><Input value={form.line1} onChange={(e) => setForm({ ...form, line1: e.target.value })} /></div>
            <div className="space-y-1.5"><Label>Address line 2 (optional)</Label><Input value={form.line2} onChange={(e) => setForm({ ...form, line2: e.target.value })} /></div>
            <div className="space-y-1.5"><Label>City</Label><Input value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} /></div>
            <div className="space-y-1.5"><Label>Access instructions (optional)</Label><Input value={form.access_instructions} onChange={(e) => setForm({ ...form, access_instructions: e.target.value })} /></div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShow(false)}>Cancel</Button>
            <Button onClick={() => save.mutate()} disabled={save.isPending || !form.line1 || !form.area}>Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
