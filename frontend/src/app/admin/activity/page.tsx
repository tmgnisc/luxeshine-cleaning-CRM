"use client";

import { PageHeader } from "@/components/shared/page-header";
import { Pagination } from "@/components/shared/pagination";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { apiGetPage } from "@/lib/api";
import { formatDateTime } from "@/lib/format";
import { useQuery } from "@tanstack/react-query";
import { History } from "lucide-react";
import { useState } from "react";

interface ActivityLog {
  id: number;
  action: string;
  user: string | null;
  subject_type: string | null;
  subject_id: number | null;
  ip: string | null;
  created_at: string;
}

export default function AdminActivityPage() {
  const [page, setPage] = useState(1);
  const [action, setAction] = useState("");

  const params = { page, per_page: 20, ...(action ? { action } : {}) };
  const list = useQuery({ queryKey: ["admin", "activity", params], queryFn: () => apiGetPage<ActivityLog>("admin/activity-logs", { params }) });

  return (
    <div className="space-y-5">
      <PageHeader title="Activity Log" subtitle="A record of administrative actions." />
      <Input placeholder="Filter by action (e.g. booking.created)…" value={action} onChange={(e) => { setAction(e.target.value); setPage(1); }} className="max-w-sm" />

      {list.isLoading ? (
        <LoadingRows />
      ) : list.data && list.data.data.length === 0 ? (
        <EmptyState title="No activity recorded" icon={History} />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow><TableHead>When</TableHead><TableHead>User</TableHead><TableHead>Action</TableHead><TableHead>Subject</TableHead><TableHead>IP</TableHead></TableRow>
            </TableHeader>
            <TableBody>
              {list.data!.data.map((a) => (
                <TableRow key={a.id}>
                  <TableCell className="whitespace-nowrap text-sm text-muted-foreground">{formatDateTime(a.created_at)}</TableCell>
                  <TableCell className="text-sm">{a.user ?? "System"}</TableCell>
                  <TableCell><Badge variant="secondary">{a.action}</Badge></TableCell>
                  <TableCell className="text-sm text-muted-foreground">{a.subject_type ? `${a.subject_type} #${a.subject_id}` : "—"}</TableCell>
                  <TableCell className="text-sm text-muted-foreground">{a.ip ?? "—"}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
      <Pagination meta={list.data?.meta} onPage={setPage} />
    </div>
  );
}
