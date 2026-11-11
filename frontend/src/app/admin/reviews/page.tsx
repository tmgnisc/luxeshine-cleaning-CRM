"use client";

import { PageHeader } from "@/components/shared/page-header";
import { Pagination } from "@/components/shared/pagination";
import { StarRating } from "@/components/shared/star-rating";
import { StatCard } from "@/components/shared/stat-card";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { apiGet, apiGetPage, apiSend } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { Review } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { MessageSquare, Star, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export default function AdminReviewsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [published, setPublished] = useState("all");
  const [rating, setRating] = useState("all");
  const [replyFor, setReplyFor] = useState<Review | null>(null);
  const [replyText, setReplyText] = useState("");

  const params = {
    page, per_page: 15,
    ...(published !== "all" ? { is_published: published } : {}),
    ...(rating !== "all" ? { rating } : {}),
  };

  const stats = useQuery({ queryKey: ["admin", "review-stats"], queryFn: () => apiGet<{ average_rating: number; published: number; awaiting: number }>("admin/reviews/stats") });
  const list = useQuery({ queryKey: ["admin", "reviews", params], queryFn: () => apiGetPage<Review>("admin/reviews", { params }) });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["admin", "reviews"] });
    qc.invalidateQueries({ queryKey: ["admin", "review-stats"] });
  };

  const togglePublish = useMutation({
    mutationFn: (id: number) => apiSend("patch", `admin/reviews/${id}/publish`),
    onSuccess: () => { invalidate(); toast.success("Review updated."); },
  });
  const remove = useMutation({
    mutationFn: (id: number) => apiSend("delete", `admin/reviews/${id}`),
    onSuccess: () => { invalidate(); toast.success("Review deleted."); },
  });
  const reply = useMutation({
    mutationFn: () => apiSend("put", `admin/reviews/${replyFor!.id}/reply`, { admin_reply: replyText }),
    onSuccess: () => { invalidate(); setReplyFor(null); toast.success("Reply saved."); },
  });

  return (
    <div className="space-y-5">
      <PageHeader title="Reviews" subtitle="Moderate customer feedback before it appears on the website." />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Average rating" value={stats.data ? `★ ${stats.data.average_rating}` : "—"} icon={Star} accent="amber" />
        <StatCard label="Published" value={stats.data?.published ?? 0} icon={MessageSquare} accent="teal" />
        <StatCard label="Awaiting moderation" value={stats.data?.awaiting ?? 0} icon={MessageSquare} />
      </div>

      <div className="flex flex-wrap gap-3">
        <Select value={published} onValueChange={(v) => { setPublished(v); setPage(1); }}>
          <SelectTrigger className="w-44"><SelectValue placeholder="Status" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All reviews</SelectItem>
            <SelectItem value="1">Published</SelectItem>
            <SelectItem value="0">Awaiting</SelectItem>
          </SelectContent>
        </Select>
        <Select value={rating} onValueChange={(v) => { setRating(v); setPage(1); }}>
          <SelectTrigger className="w-40"><SelectValue placeholder="Rating" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All ratings</SelectItem>
            {[5, 4, 3, 2, 1].map((r) => <SelectItem key={r} value={String(r)}>{r} stars</SelectItem>)}
          </SelectContent>
        </Select>
      </div>

      {list.isLoading ? (
        <LoadingRows />
      ) : list.data && list.data.data.length === 0 ? (
        <EmptyState title="No reviews found" icon={Star} />
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Customer</TableHead>
                <TableHead>Cleaner</TableHead>
                <TableHead>Rating</TableHead>
                <TableHead>Comment</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Published</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.data!.data.map((r) => (
                <TableRow key={r.id}>
                  <TableCell className="font-medium">{r.customer_name}</TableCell>
                  <TableCell>{r.cleaner_name}</TableCell>
                  <TableCell><StarRating value={r.rating} size={14} /></TableCell>
                  <TableCell className="max-w-xs">
                    <p className="line-clamp-2 text-sm text-muted-foreground">{r.comment}</p>
                    {r.admin_reply ? <p className="mt-1 text-xs text-primary">↳ {r.admin_reply}</p> : null}
                  </TableCell>
                  <TableCell className="whitespace-nowrap text-sm text-muted-foreground">{formatDate(r.created_at)}</TableCell>
                  <TableCell><Switch checked={r.is_published} onCheckedChange={() => togglePublish.mutate(r.id)} /></TableCell>
                  <TableCell>
                    <div className="flex justify-end gap-1">
                      <Button size="icon" variant="ghost" onClick={() => { setReplyFor(r); setReplyText(r.admin_reply ?? ""); }}>
                        <MessageSquare className="h-4 w-4" />
                      </Button>
                      <Button size="icon" variant="ghost" className="text-destructive"
                        onClick={() => { if (confirm("Delete this review?")) remove.mutate(r.id); }}>
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

      <Pagination meta={list.data?.meta} onPage={setPage} />

      <Dialog open={replyFor !== null} onOpenChange={(o) => !o && setReplyFor(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Reply to review</DialogTitle></DialogHeader>
          <Textarea value={replyText} onChange={(e) => setReplyText(e.target.value)} rows={4} placeholder="Write a public reply…" />
          <DialogFooter>
            <Button variant="outline" onClick={() => setReplyFor(null)}>Cancel</Button>
            <Button onClick={() => reply.mutate()} disabled={reply.isPending}>Save reply</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
