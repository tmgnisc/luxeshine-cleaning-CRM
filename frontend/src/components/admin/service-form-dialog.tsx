"use client";

import { Button } from "@/components/ui/button";
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { apiGet, apiSend, ApiError } from "@/lib/api";
import type { Service, ServiceCategory } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function ServiceFormDialog({
  service, open, onOpenChange,
}: {
  service: Service | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const qc = useQueryClient();
  const editing = !!service;
  const [form, setForm] = useState({
    category_id: "", name: "", short_description: "", description: "",
    price_per_hour: "45", default_duration_hours: "2", min_duration_hours: "1",
    max_duration_hours: "8", is_live: true,
  });

  const categories = useQuery({
    queryKey: ["admin", "categories"],
    queryFn: () => apiGet<ServiceCategory[]>("admin/service-categories"),
    enabled: open,
  });

  useEffect(() => {
    if (service) {
      setForm({
        category_id: String(service.category_id),
        name: service.name,
        short_description: service.short_description,
        description: service.description ?? "",
        price_per_hour: String(service.price_per_hour),
        default_duration_hours: String(service.default_duration_hours),
        min_duration_hours: String(service.min_duration_hours),
        max_duration_hours: String(service.max_duration_hours),
        is_live: service.is_live,
      });
    } else {
      setForm((f) => ({ ...f, name: "", short_description: "", description: "" }));
    }
  }, [service, open]);

  const save = useMutation({
    mutationFn: () => {
      const body = {
        category_id: Number(form.category_id),
        name: form.name,
        short_description: form.short_description,
        description: form.description || null,
        price_per_hour: Number(form.price_per_hour),
        default_duration_hours: Number(form.default_duration_hours),
        min_duration_hours: Number(form.min_duration_hours),
        max_duration_hours: Number(form.max_duration_hours),
        is_live: form.is_live,
      };
      return editing
        ? apiSend("put", `admin/services/${service!.id}`, body)
        : apiSend("post", "admin/services", body);
    },
    onSuccess: () => {
      toast.success(editing ? "Service updated." : "Service created.");
      qc.invalidateQueries({ queryKey: ["admin", "services"] });
      onOpenChange(false);
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not save service."),
  });

  const set = (k: string, v: string | boolean) => setForm((f) => ({ ...f, [k]: v }));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader><DialogTitle>{editing ? "Edit service" : "New service"}</DialogTitle></DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Name</Label>
            <Input value={form.name} onChange={(e) => set("name", e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Category</Label>
            <Select value={form.category_id} onValueChange={(v) => set("category_id", v)}>
              <SelectTrigger><SelectValue placeholder="Select category" /></SelectTrigger>
              <SelectContent>
                {categories.data?.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Short description</Label>
            <Input value={form.short_description} onChange={(e) => set("short_description", e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Long description</Label>
            <Textarea value={form.description} onChange={(e) => set("description", e.target.value)} rows={3} />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5"><Label>Price / hour</Label><Input type="number" value={form.price_per_hour} onChange={(e) => set("price_per_hour", e.target.value)} /></div>
            <div className="space-y-1.5"><Label>Default hours</Label><Input type="number" step="0.5" value={form.default_duration_hours} onChange={(e) => set("default_duration_hours", e.target.value)} /></div>
            <div className="space-y-1.5"><Label>Min hours</Label><Input type="number" step="0.5" value={form.min_duration_hours} onChange={(e) => set("min_duration_hours", e.target.value)} /></div>
            <div className="space-y-1.5"><Label>Max hours</Label><Input type="number" step="0.5" value={form.max_duration_hours} onChange={(e) => set("max_duration_hours", e.target.value)} /></div>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <Switch checked={form.is_live} onCheckedChange={(v) => set("is_live", v)} /> Bookable on the website
          </label>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button onClick={() => save.mutate()} disabled={save.isPending || !form.name || !form.category_id}>
            {save.isPending ? "Saving…" : "Save service"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
