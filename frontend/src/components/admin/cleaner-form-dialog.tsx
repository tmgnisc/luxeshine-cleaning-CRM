"use client";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import type { Service } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";

export function CleanerFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (o: boolean) => void }) {
  const qc = useQueryClient();
  const [form, setForm] = useState({ name: "", email: "", phone: "", bio: "", send_invite: true });
  const [skills, setSkills] = useState<number[]>([]);

  const services = useQuery({ queryKey: ["admin", "services", "all"], queryFn: () => apiGet<Service[]>("admin/services", { params: { per_page: 100 } }), enabled: open });

  const create = useMutation({
    mutationFn: () => apiSend("post", "admin/cleaners", { ...form, skills }),
    onSuccess: () => {
      toast.success("Cleaner created.");
      qc.invalidateQueries({ queryKey: ["admin", "cleaners"] });
      onOpenChange(false);
      setForm({ name: "", email: "", phone: "", bio: "", send_invite: true });
      setSkills([]);
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not create cleaner."),
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader><DialogTitle>Add cleaner</DialogTitle></DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5"><Label>Full name</Label><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5"><Label>Email</Label><Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
            <div className="space-y-1.5"><Label>Phone</Label><Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></div>
          </div>
          <div className="space-y-1.5"><Label>Bio</Label><Textarea value={form.bio} onChange={(e) => setForm({ ...form, bio: e.target.value })} rows={2} /></div>
          <div className="space-y-2">
            <Label>Skills (services)</Label>
            <div className="grid grid-cols-2 gap-2">
              {services.data?.map((s) => (
                <label key={s.id} className="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                  <Checkbox
                    checked={skills.includes(s.id)}
                    onCheckedChange={(c) => setSkills((prev) => (c ? [...prev, s.id] : prev.filter((x) => x !== s.id)))}
                  />
                  {s.name}
                </label>
              ))}
            </div>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={form.send_invite} onCheckedChange={(c) => setForm({ ...form, send_invite: Boolean(c) })} />
            Send invite email to set password
          </label>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button onClick={() => create.mutate()} disabled={create.isPending || !form.name || !form.email}>Create cleaner</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
