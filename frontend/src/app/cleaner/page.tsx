"use client";

import { BookingStatusBadge } from "@/components/shared/status-badge";
import { StatCard } from "@/components/shared/stat-card";
import { LoadingCards } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { apiGet, apiSend } from "@/lib/api";
import { formatTime } from "@/lib/format";
import type { Booking } from "@/lib/types";
import { useAuth } from "@/providers/auth-provider";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Briefcase, Star } from "lucide-react";
import Link from "next/link";
import { toast } from "sonner";

interface CleanerDashboard {
  is_available: boolean;
  rating_avg: number;
  rating_count: number;
  completed_jobs_count: number;
  today_jobs_count: number;
  upcoming_count: number;
  today_jobs: Booking[];
}

export default function CleanerDashboardPage() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ["cleaner", "dashboard"], queryFn: () => apiGet<CleanerDashboard>("cleaner/dashboard") });

  const toggle = useMutation({
    mutationFn: (value: boolean) => apiSend("put", "cleaner/availability", { is_available: value }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["cleaner", "dashboard"] }); toast.success("Availability updated."); },
  });

  const act = useMutation({
    mutationFn: ({ id, action }: { id: number; action: "start" | "complete" }) => apiSend("post", `cleaner/jobs/${id}/${action}`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["cleaner", "dashboard"] }); toast.success("Job updated."); },
    onError: (e: unknown) => toast.error(e instanceof Error ? e.message : "Action failed."),
  });

  if (isLoading || !data) return <div className="space-y-4"><LoadingCards count={3} /></div>;

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Hi {user?.name?.split(" ")[0]} 👋</h1>
          <p className="text-sm text-muted-foreground">Here are your jobs for today.</p>
        </div>
        <label className="flex items-center gap-2 rounded-full border bg-card px-4 py-2 text-sm">
          <Switch checked={data.is_available} onCheckedChange={(v) => toggle.mutate(v)} />
          {data.is_available ? "Available for work" : "Unavailable"}
        </label>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Today's jobs" value={data.today_jobs_count} icon={Briefcase} accent="blue" />
        <StatCard label="Completed jobs" value={data.completed_jobs_count} icon={Briefcase} accent="teal" />
        <StatCard label="Rating" value={`★ ${data.rating_avg}`} hint={`${data.rating_count} reviews`} icon={Star} accent="amber" />
      </div>

      <Card className="rounded-2xl">
        <CardHeader><CardTitle className="text-base">Today&apos;s schedule</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {data.today_jobs.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">No jobs scheduled today. Enjoy your day!</p>
          ) : (
            data.today_jobs.map((j) => (
              <div key={j.id} className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="font-semibold tabular-nums">{formatTime(j.start_time)}</span>
                    <span className="font-medium">{j.service?.name}</span>
                    <BookingStatusBadge status={j.status} />
                  </div>
                  <p className="text-sm text-muted-foreground">{j.customer?.name} · {j.address_snapshot?.area}</p>
                </div>
                <div className="flex gap-2">
                  <Button asChild variant="outline" size="sm"><Link href={`/cleaner/jobs/${j.id}`}>Details</Link></Button>
                  {j.status === "confirmed" ? <Button size="sm" onClick={() => act.mutate({ id: j.id, action: "start" })}>Start job</Button> : null}
                  {j.status === "in_progress" ? <Button size="sm" onClick={() => act.mutate({ id: j.id, action: "complete" })}>Complete</Button> : null}
                </div>
              </div>
            ))
          )}
        </CardContent>
      </Card>
    </div>
  );
}
