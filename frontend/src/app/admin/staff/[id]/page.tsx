"use client";

import { PageHeader } from "@/components/shared/page-header";
import { PersonAvatar } from "@/components/shared/person-avatar";
import { StarRating } from "@/components/shared/star-rating";
import { StatCard } from "@/components/shared/stat-card";
import { LoadingCards } from "@/components/shared/states";
import { VerificationBadge } from "@/components/shared/status-badge";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { apiGet } from "@/lib/api";
import { formatDate } from "@/lib/format";
import { DAY_LABELS } from "@/lib/status";
import type { Cleaner, Service, WorkingHour } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Briefcase, Star } from "lucide-react";
import Link from "next/link";
import { use } from "react";

interface CleanerDetail {
  cleaner: Cleaner;
  upcoming_jobs: Array<{ id: number; reference: string; scheduled_date: string; start_time: string; service: string; customer: string; status: string }>;
  recent_reviews: Array<{ id: number; rating: number; comment: string; created_at: string }>;
}

export default function StaffDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const { data, isLoading } = useQuery({ queryKey: ["admin", "cleaner", id], queryFn: () => apiGet<CleanerDetail>(`admin/cleaners/${id}`) });

  if (isLoading || !data) return <div className="space-y-4"><PageHeader title="Cleaner" /><LoadingCards /></div>;
  const c = data.cleaner;

  return (
    <div className="space-y-6">
      <Button asChild variant="ghost" size="sm" className="-ml-2"><Link href="/admin/staff"><ArrowLeft className="h-4 w-4" /> Back to staff</Link></Button>

      <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
        <PersonAvatar name={c.name} src={c.avatar_url} className="h-16 w-16 text-lg" />
        <div className="flex-1">
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-semibold">{c.name}</h1>
            <VerificationBadge status={c.verification_status} />
          </div>
          <p className="text-sm text-muted-foreground">{c.email} · {c.phone} · joined {formatDate(c.joined_at)}</p>
          {c.bio ? <p className="mt-2 max-w-2xl text-sm">{c.bio}</p> : null}
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Completed jobs" value={c.completed_jobs_count} icon={Briefcase} accent="teal" />
        <StatCard label="Rating" value={`★ ${c.rating_avg}`} hint={`${c.rating_count} reviews`} icon={Star} accent="amber" />
        <StatCard label="Availability" value={c.is_available ? "Available" : "Unavailable"} />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="rounded-2xl">
          <CardHeader><CardTitle className="text-base">Skills</CardTitle></CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            {(c.skills as Service[])?.map((s) => <Badge key={s.id} variant="secondary">{s.name}</Badge>)}
            {(!c.skills || c.skills.length === 0) && <p className="text-sm text-muted-foreground">No skills assigned.</p>}
          </CardContent>
        </Card>

        <Card className="rounded-2xl">
          <CardHeader><CardTitle className="text-base">Working hours</CardTitle></CardHeader>
          <CardContent className="space-y-1.5 text-sm">
            {(c.working_hours as WorkingHour[])?.sort((a, b) => a.day_of_week - b.day_of_week).map((h) => (
              <div key={h.day_of_week} className="flex justify-between">
                <span className="text-muted-foreground">{DAY_LABELS[h.day_of_week]}</span>
                <span>{h.is_off ? "Off" : `${h.start_time}–${h.end_time}`}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="rounded-2xl">
          <CardHeader><CardTitle className="text-base">Upcoming jobs</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {data.upcoming_jobs.length === 0 ? <p className="text-sm text-muted-foreground">No upcoming jobs.</p> : data.upcoming_jobs.map((j) => (
              <div key={j.id} className="flex items-center justify-between rounded-xl border px-3 py-2 text-sm">
                <span>{j.reference} · {j.service}</span>
                <span className="text-muted-foreground">{formatDate(j.scheduled_date)} {j.start_time}</span>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card className="rounded-2xl">
          <CardHeader><CardTitle className="text-base">Recent reviews</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {data.recent_reviews.length === 0 ? <p className="text-sm text-muted-foreground">No reviews yet.</p> : data.recent_reviews.map((r) => (
              <div key={r.id} className="rounded-xl border px-3 py-2">
                <StarRating value={r.rating} size={14} />
                <p className="mt-1 text-sm text-muted-foreground">{r.comment}</p>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
