"use client";

import { BookingStatusBadge } from "@/components/shared/status-badge";
import { EmptyState, LoadingCards } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { useCurrency } from "@/hooks/use-settings";
import { apiGetPage } from "@/lib/api";
import { formatDate, formatTime } from "@/lib/format";
import type { Booking } from "@/lib/types";
import { useAuth } from "@/providers/auth-provider";
import { useQuery } from "@tanstack/react-query";
import { CalendarCheck, CalendarPlus } from "lucide-react";
import Link from "next/link";

export default function AccountOverviewPage() {
  const { user } = useAuth();
  const currency = useCurrency();
  const upcoming = useQuery({
    queryKey: ["customer", "bookings", "upcoming"],
    queryFn: () => apiGetPage<Booking>("customer/bookings", { params: { filter: "upcoming", per_page: 1 } }),
  });

  const next = upcoming.data?.data[0];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Hi {user?.name?.split(" ")[0]} 👋</h1>
        <p className="mt-1 text-sm text-muted-foreground">Here&apos;s what&apos;s next for you.</p>
      </div>

      {upcoming.isLoading ? (
        <LoadingCards count={1} />
      ) : next ? (
        <Card className="rounded-2xl">
          <CardContent className="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary"><CalendarCheck className="h-6 w-6" /></div>
              <div>
                <div className="flex items-center gap-2">
                  <p className="font-semibold">{next.service?.name}</p>
                  <BookingStatusBadge status={next.status} />
                </div>
                <p className="text-sm text-muted-foreground">{formatDate(next.scheduled_date)} at {formatTime(next.start_time)}</p>
                <p className="text-sm text-muted-foreground">{next.cleaner?.name ? `Cleaner: ${next.cleaner.name}` : "Awaiting cleaner assignment"} · {currency(next.total_price)}</p>
              </div>
            </div>
            <Button asChild variant="outline"><Link href={`/account/bookings/${next.id}`}>View details</Link></Button>
          </CardContent>
        </Card>
      ) : (
        <EmptyState
          title="No upcoming bookings"
          description="Book your next cleaning in just a few taps."
          icon={CalendarPlus}
          action={<Button asChild><Link href="/book">Book a cleaning</Link></Button>}
        />
      )}

      <div className="flex gap-3">
        <Button asChild><Link href="/book"><CalendarPlus className="h-4 w-4" /> Book again</Link></Button>
        <Button asChild variant="outline"><Link href="/account/bookings">All bookings</Link></Button>
      </div>
    </div>
  );
}
