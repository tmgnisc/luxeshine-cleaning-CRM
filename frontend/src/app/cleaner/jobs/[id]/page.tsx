"use client";

import { BookingStatusBadge } from "@/components/shared/status-badge";
import { LoadingCards } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { apiGet, apiSend } from "@/lib/api";
import { formatDate, formatTime } from "@/lib/format";
import type { Booking } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, MapPin, Phone } from "lucide-react";
import Link from "next/link";
import { use } from "react";
import { toast } from "sonner";

export default function CleanerJobDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const qc = useQueryClient();
  const { data: j, isLoading } = useQuery({ queryKey: ["cleaner", "job", id], queryFn: () => apiGet<Booking>(`cleaner/jobs/${id}`) });

  const act = useMutation({
    mutationFn: (action: "start" | "complete") => apiSend("post", `cleaner/jobs/${id}/${action}`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["cleaner", "job", id] }); qc.invalidateQueries({ queryKey: ["cleaner"] }); toast.success("Job updated."); },
    onError: (e: unknown) => toast.error(e instanceof Error ? e.message : "Action failed."),
  });

  if (isLoading || !j) return <LoadingCards count={1} />;

  const addr = j.address_snapshot;
  const mapsQuery = addr ? encodeURIComponent(`${addr.line1}, ${addr.area}, ${addr.city}`) : "";

  return (
    <div className="space-y-6">
      <Button asChild variant="ghost" size="sm" className="-ml-2"><Link href="/cleaner/jobs"><ArrowLeft className="h-4 w-4" /> Back to jobs</Link></Button>

      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2"><h1 className="text-2xl font-semibold">{j.service?.name}</h1><BookingStatusBadge status={j.status} /></div>
          <p className="text-sm text-muted-foreground">{formatDate(j.scheduled_date)} · {formatTime(j.start_time)}–{formatTime(j.end_time)}</p>
        </div>
      </div>

      <Card className="rounded-2xl">
        <CardHeader><CardTitle className="text-base">Customer</CardTitle></CardHeader>
        <CardContent className="space-y-3 text-sm">
          <p className="font-medium">{j.customer?.name}</p>
          {j.customer?.phone ? (
            <a href={`tel:${j.customer.phone}`} className="flex items-center gap-2 text-primary"><Phone className="h-4 w-4" /> {j.customer.phone}</a>
          ) : null}
          {addr ? (
            <a href={`https://www.google.com/maps/search/?api=1&query=${mapsQuery}`} target="_blank" rel="noopener noreferrer" className="flex items-start gap-2 text-primary">
              <MapPin className="mt-0.5 h-4 w-4" /> {addr.line1}, {addr.area}, {addr.city}
            </a>
          ) : null}
          {j.customer_notes ? <div className="rounded-xl bg-muted/50 p-3 text-muted-foreground">{j.customer_notes}</div> : null}
        </CardContent>
      </Card>

      {j.status === "confirmed" ? <Button size="lg" className="w-full" onClick={() => act.mutate("start")}>Start job</Button> : null}
      {j.status === "in_progress" ? <Button size="lg" className="w-full" onClick={() => act.mutate("complete")}>Mark as complete</Button> : null}
    </div>
  );
}
