"use client";

import { CleanerFormDialog } from "@/components/admin/cleaner-form-dialog";
import { PageHeader } from "@/components/shared/page-header";
import { Pagination } from "@/components/shared/pagination";
import { PersonAvatar } from "@/components/shared/person-avatar";
import { StatCard } from "@/components/shared/stat-card";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { apiGet, apiGetPage, apiSend } from "@/lib/api";
import type { Cleaner, VerificationStatus } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { UserCog } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";

export default function AdminStaffPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [verification, setVerification] = useState("all");
  const [search, setSearch] = useState("");
  const [showNew, setShowNew] = useState(false);

  const params = { page, per_page: 15, ...(verification !== "all" ? { verification_status: verification } : {}), ...(search ? { search } : {}) };
  const list = useQuery({ queryKey: ["admin", "cleaners", params], queryFn: () => apiGetPage<Cleaner>("admin/cleaners", { params }) });
  const counts = useQuery({ queryKey: ["admin", "verification-counts"], queryFn: () => apiGet<{ verified: number; pending: number; rejected: number }>("admin/cleaners/verification-counts") });

  const invalidate = () => { qc.invalidateQueries({ queryKey: ["admin", "cleaners"] }); qc.invalidateQueries({ queryKey: ["admin", "verification-counts"] }); };

  const setVerify = useMutation({
    mutationFn: ({ id, status }: { id: number; status: VerificationStatus }) => apiSend("patch", `admin/cleaners/${id}/verification`, { verification_status: status }),
    onSuccess: () => { invalidate(); toast.success("Verification updated."); },
  });
  const setAvail = useMutation({
    mutationFn: ({ id, value }: { id: number; value: boolean }) => apiSend("patch", `admin/cleaners/${id}/availability`, { is_available: value }),
    onSuccess: () => invalidate(),
  });

  return (
    <div className="space-y-5">
      <PageHeader title="Staff" subtitle={`${counts.data?.verified ?? 0} verified cleaners`} actions={<Button onClick={() => setShowNew(true)}><UserCog className="h-4 w-4" /> Add cleaner</Button>} />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Verified" value={counts.data?.verified ?? 0} accent="teal" />
        <StatCard label="Pending" value={counts.data?.pending ?? 0} accent="amber" />
        <StatCard label="Rejected" value={counts.data?.rejected ?? 0} />
      </div>

      <div className="flex flex-wrap gap-3">
        <Input placeholder="Search staff…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} className="max-w-xs" />
        <Select value={verification} onValueChange={(v) => { setVerification(v); setPage(1); }}>
          <SelectTrigger className="w-44"><SelectValue placeholder="Verification" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="verified">Verified</SelectItem>
            <SelectItem value="pending">Pending</SelectItem>
            <SelectItem value="rejected">Rejected</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {list.isLoading ? (
        <LoadingRows />
      ) : list.data && list.data.data.length === 0 ? (
        <EmptyState title="No cleaners found" icon={UserCog} />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Cleaner</TableHead><TableHead>Contact</TableHead><TableHead>Skills</TableHead>
                <TableHead>Rating</TableHead><TableHead>Jobs</TableHead><TableHead>Verification</TableHead>
                <TableHead>Available</TableHead><TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.data!.data.map((c) => (
                <TableRow key={c.id}>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <PersonAvatar name={c.name} src={c.avatar_url} />
                      <div><div className="font-medium">{c.name}</div><div className="text-xs text-muted-foreground">joined {c.joined_at}</div></div>
                    </div>
                  </TableCell>
                  <TableCell className="text-sm text-muted-foreground">{c.phone}<span className="block">{c.email}</span></TableCell>
                  <TableCell>
                    <div className="flex max-w-[180px] flex-wrap gap-1">
                      {(c.skills as Array<{ id: number; name: string }>)?.slice(0, 3).map((s) => <Badge key={s.id} variant="secondary" className="text-xs">{s.name}</Badge>)}
                    </div>
                  </TableCell>
                  <TableCell className="whitespace-nowrap text-sm">★ {c.rating_avg} <span className="text-muted-foreground">({c.rating_count})</span></TableCell>
                  <TableCell className="text-sm">{c.completed_jobs_count} done<span className="block text-xs text-muted-foreground">{c.upcoming_jobs_count ?? 0} upcoming</span></TableCell>
                  <TableCell>
                    <Select value={c.verification_status} onValueChange={(v) => setVerify.mutate({ id: c.id, status: v as VerificationStatus })}>
                      <SelectTrigger className="h-8 w-32"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="verified">Verified</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="rejected">Rejected</SelectItem>
                      </SelectContent>
                    </Select>
                  </TableCell>
                  <TableCell><Switch checked={c.is_available} onCheckedChange={(v) => setAvail.mutate({ id: c.id, value: v })} /></TableCell>
                  <TableCell className="text-right"><Button size="sm" variant="outline" asChild><Link href={`/admin/staff/${c.id}`}>View</Link></Button></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
      <Pagination meta={list.data?.meta} onPage={setPage} />
      <CleanerFormDialog open={showNew} onOpenChange={setShowNew} />
    </div>
  );
}
