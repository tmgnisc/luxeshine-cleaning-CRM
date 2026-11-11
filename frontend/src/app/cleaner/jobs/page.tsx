"use client";

import { BookingStatusBadge } from "@/components/shared/status-badge";
import { Pagination } from "@/components/shared/pagination";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { apiGetPage } from "@/lib/api";
import { formatDate, formatTime } from "@/lib/format";
import type { Booking } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { CalendarClock } from "lucide-react";
import Link from "next/link";
import { useState } from "react";

export default function CleanerJobsPage() {
  const [page, setPage] = useState(1);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");

  const params = { page, per_page: 20, sort: "-scheduled_date", ...(from ? { date_from: from } : {}), ...(to ? { date_to: to } : {}) };
  const { data, isLoading } = useQuery({ queryKey: ["cleaner", "jobs", params], queryFn: () => apiGetPage<Booking>("cleaner/jobs", { params }) });

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold tracking-tight">My jobs</h1>

      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-1"><label className="text-xs text-muted-foreground">From</label><Input type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1); }} className="w-40" /></div>
        <div className="space-y-1"><label className="text-xs text-muted-foreground">To</label><Input type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1); }} className="w-40" /></div>
      </div>

      {isLoading ? (
        <LoadingRows />
      ) : data && data.data.length === 0 ? (
        <EmptyState title="No jobs found" icon={CalendarClock} />
      ) : (
        <div className="space-y-3">
          {data?.data.map((j) => (
            <Link key={j.id} href={`/cleaner/jobs/${j.id}`}>
              <Card className="rounded-2xl transition-shadow hover:shadow-sm">
                <CardContent className="flex items-center justify-between gap-4 p-5">
                  <div>
                    <div className="flex items-center gap-2">
                      <p className="font-semibold">{j.service?.name}</p>
                      <BookingStatusBadge status={j.status} />
                    </div>
                    <p className="text-sm text-muted-foreground">{formatDate(j.scheduled_date)} at {formatTime(j.start_time)} · {j.customer?.name}</p>
                  </div>
                  <span className="text-sm text-muted-foreground">{j.address_snapshot?.area}</span>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      )}
      <Pagination meta={data?.meta} onPage={setPage} />
    </div>
  );
}
