"use client";

import { BulkActionBar } from "@/components/shared/bulk-action-bar";
import { PageHeader } from "@/components/shared/page-header";
import { Pagination } from "@/components/shared/pagination";
import { PersonAvatar } from "@/components/shared/person-avatar";
import { BookingStatusBadge } from "@/components/shared/status-badge";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { useBulkSelect } from "@/hooks/use-bulk-select";
import { useCurrency } from "@/hooks/use-settings";
import { apiGetPage, apiSend, ApiError } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { Booking, BulkDeleteResult, Customer } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Users } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export default function AdminCustomersPage() {
  const qc = useQueryClient();
  const currency = useCurrency();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [showNew, setShowNew] = useState(false);
  const [historyFor, setHistoryFor] = useState<Customer | null>(null);

  const params = { page, per_page: 15, sort: "-created_at", ...(search ? { search } : {}) };
  const list = useQuery({ queryKey: ["admin", "customers", params], queryFn: () => apiGetPage<Customer>("admin/customers", { params }) });

  const [form, setForm] = useState({ name: "", email: "", phone: "", send_invite: false });
  const create = useMutation({
    mutationFn: () => apiSend("post", "admin/customers", { ...form }),
    onSuccess: () => { toast.success("Customer created."); qc.invalidateQueries({ queryKey: ["admin", "customers"] }); setShowNew(false); setForm({ name: "", email: "", phone: "", send_invite: false }); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not create customer."),
  });

  const rows = list.data?.data ?? [];
  const bulk = useBulkSelect(rows.map((c) => c.id));
  const bulkDelete = useMutation({
    mutationFn: () => apiSend<BulkDeleteResult>("post", "admin/customers/bulk-delete", { ids: bulk.ids }),
    onSuccess: (res) => {
      toast.success(`Removed ${res.deleted} customer${res.deleted === 1 ? "" : "s"}.`);
      bulk.clear();
      qc.invalidateQueries({ queryKey: ["admin", "customers"] });
    },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not delete selection."),
  });

  return (
    <div className="space-y-5">
      <PageHeader
        title="Customers"
        subtitle={list.data ? `${list.data.meta.total} registered customers` : undefined}
        actions={<Button onClick={() => setShowNew(true)}><Plus className="h-4 w-4" /> Add customer</Button>}
      />

      <Input placeholder="Search by name, email or phone…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} className="max-w-sm" />

      {list.isLoading ? (
        <LoadingRows />
      ) : list.data && list.data.data.length === 0 ? (
        <EmptyState title="No customers found" icon={Users} />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10">
                  <Checkbox
                    checked={bulk.allOnPageSelected ? true : bulk.someOnPageSelected ? "indeterminate" : false}
                    onCheckedChange={() => bulk.toggleAll()}
                    aria-label="Select all"
                  />
                </TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Contact</TableHead>
                <TableHead>Since</TableHead>
                <TableHead>Bookings</TableHead>
                <TableHead>Lifetime spend</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.data!.data.map((c) => (
                <TableRow key={c.id} data-state={bulk.isSelected(c.id) ? "selected" : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={bulk.isSelected(c.id)}
                      onCheckedChange={() => bulk.toggle(c.id)}
                      aria-label={`Select ${c.name}`}
                    />
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <PersonAvatar name={c.name} />
                      <span className="font-medium">{c.name}</span>
                    </div>
                  </TableCell>
                  <TableCell className="text-sm text-muted-foreground">
                    {c.email}<span className="block">{c.phone}</span>
                  </TableCell>
                  <TableCell className="text-sm">{formatDate(c.customer_since)}</TableCell>
                  <TableCell>{c.bookings_count ?? 0}</TableCell>
                  <TableCell>{currency(c.lifetime_spend ?? 0)}</TableCell>
                  <TableCell className="text-right">
                    <Button size="sm" variant="outline" onClick={() => setHistoryFor(c)}>History</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <Pagination meta={list.data?.meta} onPage={setPage} />

      <BulkActionBar
        count={bulk.count}
        noun="customer"
        loading={bulkDelete.isPending}
        onClear={bulk.clear}
        onDelete={() => bulkDelete.mutate()}
      />

      {/* Create dialog */}
      <Dialog open={showNew} onOpenChange={setShowNew}>
        <DialogContent>
          <DialogHeader><DialogTitle>Add customer</DialogTitle></DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5"><Label>Name</Label><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
            <div className="space-y-1.5"><Label>Email</Label><Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
            <div className="space-y-1.5"><Label>Phone</Label><Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></div>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={form.send_invite} onChange={(e) => setForm({ ...form, send_invite: e.target.checked })} />
              Send account invite email
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowNew(false)}>Cancel</Button>
            <Button onClick={() => create.mutate()} disabled={create.isPending || !form.name || !form.email}>Create</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* History drawer */}
      <CustomerHistorySheet customer={historyFor} onClose={() => setHistoryFor(null)} currency={currency} />
    </div>
  );
}

function CustomerHistorySheet({ customer, onClose, currency }: { customer: Customer | null; onClose: () => void; currency: (n: number) => string }) {
  const { data } = useQuery({
    queryKey: ["admin", "customer-bookings", customer?.id],
    queryFn: () => apiGetPage<Booking>(`admin/customers/${customer!.id}/bookings`, { params: { per_page: 50 } }),
    enabled: !!customer,
  });

  return (
    <Sheet open={customer !== null} onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <SheetHeader><SheetTitle>{customer?.name} · booking history</SheetTitle></SheetHeader>
        <div className="space-y-2 py-4">
          {data?.data.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No bookings yet.</p>
          ) : (
            data?.data.map((b) => (
              <div key={b.id} className="flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5 text-sm">
                <div>
                  <span className="font-medium">{b.reference}</span>
                  <span className="block text-xs text-muted-foreground">{formatDate(b.scheduled_date)} · {b.service?.name}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span>{currency(b.total_price)}</span>
                  <BookingStatusBadge status={b.status} />
                </div>
              </div>
            ))
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
