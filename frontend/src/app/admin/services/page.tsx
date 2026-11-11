"use client";

import { ServiceFormDialog } from "@/components/admin/service-form-dialog";
import { PageHeader } from "@/components/shared/page-header";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useCurrency } from "@/hooks/use-settings";
import { apiGetPage, apiSend } from "@/lib/api";
import type { Service } from "@/lib/types";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useQuery } from "@tanstack/react-query";
import { Pencil, Plus, Sparkles, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export default function AdminServicesPage() {
  const qc = useQueryClient();
  const currency = useCurrency();
  const [editing, setEditing] = useState<Service | null>(null);
  const [showForm, setShowForm] = useState(false);

  const list = useQuery({
    queryKey: ["admin", "services", "list"],
    queryFn: () => apiGetPage<Service>("admin/services", { params: { per_page: 100, sort: "sort_order" } }),
  });

  const toggleLive = useMutation({
    mutationFn: (id: number) => apiSend("patch", `admin/services/${id}/live`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "services"] }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => apiSend("delete", `admin/services/${id}`),
    onSuccess: () => { toast.success("Service deleted."); qc.invalidateQueries({ queryKey: ["admin", "services"] }); },
    onError: (e: unknown) => toast.error(e instanceof Error ? e.message : "Cannot delete this service."),
  });

  const liveCount = list.data?.data.filter((s) => s.is_live).length ?? 0;

  return (
    <div className="space-y-5">
      <PageHeader
        title="Services"
        subtitle={`${liveCount} live service${liveCount === 1 ? "" : "s"}`}
        actions={
          <Button onClick={() => { setEditing(null); setShowForm(true); }}>
            <Plus className="h-4 w-4" /> Add service
          </Button>
        }
      />

      {list.isLoading ? (
        <LoadingRows />
      ) : list.data && list.data.data.length === 0 ? (
        <EmptyState title="No services yet" icon={Sparkles} action={<Button onClick={() => setShowForm(true)}>Add your first service</Button>} />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Service</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Price/hr</TableHead>
                <TableHead>Default</TableHead>
                <TableHead>Bookings</TableHead>
                <TableHead>Live</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.data!.data.map((s) => (
                <TableRow key={s.id}>
                  <TableCell>
                    <div className="font-medium">{s.name}</div>
                    <div className="max-w-xs truncate text-xs text-muted-foreground">{s.short_description}</div>
                  </TableCell>
                  <TableCell><Badge variant="secondary">{s.category?.name ?? "—"}</Badge></TableCell>
                  <TableCell>{currency(s.price_per_hour)}</TableCell>
                  <TableCell>{s.default_duration_hours}h</TableCell>
                  <TableCell>{s.bookings_count ?? 0}</TableCell>
                  <TableCell><Switch checked={s.is_live} onCheckedChange={() => toggleLive.mutate(s.id)} /></TableCell>
                  <TableCell>
                    <div className="flex justify-end gap-1">
                      <Button size="icon" variant="ghost" onClick={() => { setEditing(s); setShowForm(true); }}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button size="icon" variant="ghost" className="text-destructive"
                        onClick={() => { if (confirm(`Delete ${s.name}?`)) remove.mutate(s.id); }}>
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

      <ServiceFormDialog service={editing} open={showForm} onOpenChange={(o) => { setShowForm(o); if (!o) setEditing(null); }} />
    </div>
  );
}
