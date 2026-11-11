"use client";

import { PageHeader } from "@/components/shared/page-header";
import { PersonAvatar } from "@/components/shared/person-avatar";
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
import { apiGetPage, apiSend, ApiError } from "@/lib/api";
import type { User } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ShieldCheck, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export default function AdminUsersPage() {
  const qc = useQueryClient();
  const [showNew, setShowNew] = useState(false);
  const [form, setForm] = useState({ name: "", email: "", role: "admin", send_invite: true });

  const list = useQuery({ queryKey: ["admin", "users"], queryFn: () => apiGetPage<User>("admin/users", { params: { per_page: 50 } }) });
  const invalidate = () => qc.invalidateQueries({ queryKey: ["admin", "users"] });

  const create = useMutation({
    mutationFn: () => apiSend("post", "admin/users", form),
    onSuccess: () => { toast.success("Admin user created."); invalidate(); setShowNew(false); setForm({ name: "", email: "", role: "admin", send_invite: true }); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not create user."),
  });
  const update = useMutation({
    mutationFn: ({ id, body }: { id: number; body: Record<string, unknown> }) => apiSend("patch", `admin/users/${id}`, body),
    onSuccess: () => { invalidate(); toast.success("Updated."); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Update failed."),
  });
  const remove = useMutation({
    mutationFn: (id: number) => apiSend("delete", `admin/users/${id}`),
    onSuccess: () => { invalidate(); toast.success("User removed."); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Delete failed."),
  });

  return (
    <div className="space-y-5">
      <PageHeader title="Admin Users" subtitle="Manage administrator accounts." actions={<Button onClick={() => setShowNew(true)}><ShieldCheck className="h-4 w-4" /> Invite admin</Button>} />

      {list.isLoading ? (
        <LoadingRows />
      ) : list.data && list.data.data.length === 0 ? (
        <EmptyState title="No admin users" icon={ShieldCheck} />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead><TableHead>Email</TableHead><TableHead>Role</TableHead>
                <TableHead>Active</TableHead><TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.data!.data.map((u) => (
                <TableRow key={u.id}>
                  <TableCell><div className="flex items-center gap-3"><PersonAvatar name={u.name} src={u.avatar_url} /><span className="font-medium">{u.name}</span></div></TableCell>
                  <TableCell className="text-sm text-muted-foreground">{u.email}</TableCell>
                  <TableCell>
                    <Select value={u.role} onValueChange={(v) => update.mutate({ id: u.id, body: { role: v } })}>
                      <SelectTrigger className="h-8 w-36"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="admin">Admin</SelectItem>
                        <SelectItem value="super_admin">Super Admin</SelectItem>
                      </SelectContent>
                    </Select>
                  </TableCell>
                  <TableCell><Switch checked={u.is_active} onCheckedChange={(v) => update.mutate({ id: u.id, body: { is_active: v } })} /></TableCell>
                  <TableCell className="text-right">
                    <Button size="icon" variant="ghost" className="text-destructive" onClick={() => { if (confirm(`Remove ${u.name}?`)) remove.mutate(u.id); }}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <Dialog open={showNew} onOpenChange={setShowNew}>
        <DialogContent>
          <DialogHeader><DialogTitle>Invite admin user</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5"><Label>Name</Label><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
            <div className="space-y-1.5"><Label>Email</Label><Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
            <div className="space-y-1.5">
              <Label>Role</Label>
              <Select value={form.role} onValueChange={(v) => setForm({ ...form, role: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent><SelectItem value="admin">Admin</SelectItem><SelectItem value="super_admin">Super Admin</SelectItem></SelectContent>
              </Select>
            </div>
            <Badge variant="secondary">An invite email with a set-password link will be sent.</Badge>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowNew(false)}>Cancel</Button>
            <Button onClick={() => create.mutate()} disabled={create.isPending || !form.name || !form.email}>Send invite</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
