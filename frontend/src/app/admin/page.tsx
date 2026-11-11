"use client";

import { PageHeader } from "@/components/shared/page-header";
import { StatCard } from "@/components/shared/stat-card";
import { BookingStatusBadge } from "@/components/shared/status-badge";
import { LoadingCards } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useCurrency } from "@/hooks/use-settings";
import { apiGet } from "@/lib/api";
import { formatTime } from "@/lib/format";
import type { Booking, DashboardStats } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import {
  Activity,
  AlertTriangle,
  CalendarPlus,
  ClipboardList,
  DollarSign,
  Users,
} from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import {
  Bar,
  CartesianGrid,
  Cell,
  ComposedChart,
  Line,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

const CHART_COLORS = ["#1F4C85", "#3b82f6", "#f59e0b", "#14b8a6", "#8b5cf6", "#ec4899"];

export default function AdminDashboardPage() {
  const currency = useCurrency();
  const [months, setMonths] = useState(6);

  const stats = useQuery({ queryKey: ["admin", "stats"], queryFn: () => apiGet<DashboardStats>("admin/dashboard/stats") });
  const revenue = useQuery({
    queryKey: ["admin", "revenue", months],
    queryFn: () => apiGet<Array<{ label: string; revenue: number; bookings: number }>>(`admin/dashboard/revenue-chart?months=${months}`),
  });
  const byService = useQuery({
    queryKey: ["admin", "by-service"],
    queryFn: () => apiGet<Array<{ service: string; bookings: number }>>("admin/dashboard/bookings-by-service"),
  });
  const today = useQuery({ queryKey: ["admin", "today"], queryFn: () => apiGet<Booking[]>("admin/dashboard/today-jobs") });
  const topCleaners = useQuery({
    queryKey: ["admin", "top-cleaners"],
    queryFn: () => apiGet<Array<{ name: string; completed_jobs: number }>>("admin/dashboard/top-cleaners?limit=5"),
  });

  const s = stats.data;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Dashboard"
        subtitle="Your cleaning operations at a glance."
        actions={
          <>
            <Button asChild variant="outline">
              <Link href="/admin/schedule">View schedule</Link>
            </Button>
            <Button asChild>
              <Link href="/admin/bookings?new=1">
                <CalendarPlus className="h-4 w-4" /> New booking
              </Link>
            </Button>
          </>
        }
      />

      {/* Alerts */}
      {s && (s.alerts.unassigned_soon > 0 || s.alerts.reviews_awaiting > 0 || s.alerts.overdue > 0) ? (
        <div className="flex flex-wrap gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
          <AlertTriangle className="h-5 w-5 text-amber-600" />
          {s.alerts.unassigned_soon > 0 && (
            <Link href="/admin/bookings?unassigned=1" className="font-medium text-amber-800 hover:underline">
              {s.alerts.unassigned_soon} unassigned booking(s) in the next 48h
            </Link>
          )}
          {s.alerts.reviews_awaiting > 0 && (
            <Link href="/admin/reviews?is_published=0" className="font-medium text-amber-800 hover:underline">
              • {s.alerts.reviews_awaiting} review(s) awaiting moderation
            </Link>
          )}
          {s.alerts.overdue > 0 && <span className="font-medium text-amber-800">• {s.alerts.overdue} overdue</span>}
        </div>
      ) : null}

      {/* Stat cards */}
      {stats.isLoading || !s ? (
        <LoadingCards count={5} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <StatCard label="Total bookings" value={s.total_bookings} hint={`${s.today_scheduled} scheduled today`} icon={ClipboardList} />
          <StatCard label="Revenue" value={currency(s.revenue)} hint={`${currency(s.collected)} collected`} icon={DollarSign} accent="teal" />
          <StatCard label="Active jobs" value={s.active_jobs} hint="cleaners on site now" icon={Activity} accent="blue" />
          <StatCard label="Customers" value={s.customers} icon={Users} />
          <StatCard label="Staff" value={s.staff_total} hint={`${s.staff_available} available`} icon={Users} accent="amber" />
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="rounded-2xl lg:col-span-2">
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle className="text-base">Revenue & bookings</CardTitle>
            <div className="flex gap-1">
              {[3, 6, 12].map((m) => (
                <Button key={m} size="sm" variant={months === m ? "default" : "outline"} onClick={() => setMonths(m)}>
                  {m}m
                </Button>
              ))}
            </div>
          </CardHeader>
          <CardContent className="h-72">
            {revenue.data ? (
              <ResponsiveContainer width="100%" height="100%">
                <ComposedChart data={revenue.data}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e7e2d6" />
                  <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                  <YAxis yAxisId="left" tick={{ fontSize: 12 }} />
                  <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 12 }} />
                  <Tooltip />
                  <Bar yAxisId="left" dataKey="revenue" fill="#1F4C85" radius={[6, 6, 0, 0]} name="Revenue" />
                  <Line yAxisId="right" type="monotone" dataKey="bookings" stroke="#3b82f6" strokeWidth={2} name="Bookings" />
                </ComposedChart>
              </ResponsiveContainer>
            ) : null}
          </CardContent>
        </Card>

        <Card className="rounded-2xl">
          <CardHeader>
            <CardTitle className="text-base">Bookings by service</CardTitle>
          </CardHeader>
          <CardContent className="h-72">
            {byService.data && byService.data.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie data={byService.data} dataKey="bookings" nameKey="service" innerRadius={55} outerRadius={85} paddingAngle={2}>
                    {byService.data.map((_, i) => (
                      <Cell key={i} fill={CHART_COLORS[i % CHART_COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <p className="pt-12 text-center text-sm text-muted-foreground">No data yet.</p>
            )}
            <div className="mt-2 flex flex-wrap gap-2">
              {byService.data?.map((row, i) => (
                <span key={row.service} className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <span className="h-2.5 w-2.5 rounded-full" style={{ background: CHART_COLORS[i % CHART_COLORS.length] }} />
                  {row.service}
                </span>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="rounded-2xl lg:col-span-2">
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle className="text-base">Today&apos;s jobs</CardTitle>
            <Button asChild variant="ghost" size="sm">
              <Link href="/admin/bookings">View all</Link>
            </Button>
          </CardHeader>
          <CardContent className="space-y-2">
            {today.data && today.data.length > 0 ? (
              today.data.map((b) => (
                <div key={b.id} className="flex items-center justify-between gap-3 rounded-xl border border-border/60 px-3 py-2.5 text-sm">
                  <div className="flex items-center gap-3">
                    <span className="tabular-nums text-muted-foreground">{formatTime(b.start_time)}</span>
                    <span className="font-medium">{b.customer?.name ?? "—"}</span>
                    <span className="text-muted-foreground">· {b.service?.name}</span>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="text-muted-foreground">{b.cleaner?.name ?? "Unassigned"}</span>
                    <BookingStatusBadge status={b.status} />
                  </div>
                </div>
              ))
            ) : (
              <p className="py-8 text-center text-sm text-muted-foreground">No jobs scheduled today.</p>
            )}
          </CardContent>
        </Card>

        <Card className="rounded-2xl">
          <CardHeader>
            <CardTitle className="text-base">Top cleaners</CardTitle>
          </CardHeader>
          <CardContent className="h-64">
            {topCleaners.data && topCleaners.data.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <ComposedChart layout="vertical" data={topCleaners.data} margin={{ left: 10 }}>
                  <XAxis type="number" hide />
                  <YAxis type="category" dataKey="name" width={90} tick={{ fontSize: 11 }} />
                  <Tooltip />
                  <Bar dataKey="completed_jobs" fill="#1F4C85" radius={[0, 6, 6, 0]} name="Completed jobs" />
                </ComposedChart>
              </ResponsiveContainer>
            ) : (
              <p className="pt-10 text-center text-sm text-muted-foreground">No data yet.</p>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
