"use client";

import { BookingStatusBadge } from "@/components/shared/status-badge";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useCurrency } from "@/hooks/use-settings";
import { apiGetPage } from "@/lib/api";
import { formatDate, formatTime } from "@/lib/format";
import type { Booking } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { CalendarPlus } from "lucide-react";
import Link from "next/link";
import { useState } from "react";

export default function AccountBookingsPage() {
  const currency = useCurrency();
  const [filter, setFilter] = useState("upcoming");

  const { data, isLoading } = useQuery({
    queryKey: ["customer", "bookings", filter],
    queryFn: () => apiGetPage<Booking>("customer/bookings", { params: { filter, per_page: 50 } }),
  });

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold tracking-tight">My bookings</h1>
        <Button asChild><Link href="/book"><CalendarPlus className="h-4 w-4" /> New booking</Link></Button>
      </div>

      <Tabs value={filter} onValueChange={setFilter}>
        <TabsList>
          <TabsTrigger value="upcoming">Upcoming</TabsTrigger>
          <TabsTrigger value="past">Past</TabsTrigger>
          <TabsTrigger value="cancelled">Cancelled</TabsTrigger>
        </TabsList>
      </Tabs>

      {isLoading ? (
        <LoadingRows />
      ) : data && data.data.length === 0 ? (
        <EmptyState title="Nothing here yet" description="Your bookings will appear here." icon={CalendarPlus} action={<Button asChild><Link href="/book">Book now</Link></Button>} />
      ) : (
        <div className="space-y-3">
          {data?.data.map((b) => (
            <Card key={b.id} className="rounded-2xl transition-shadow hover:shadow-sm">
              <CardContent className="flex items-center justify-between gap-4 p-5">
                <div>
                  <div className="flex items-center gap-2">
                    <p className="font-semibold">{b.service?.name}</p>
                    <BookingStatusBadge status={b.status} />
                  </div>
                  <p className="text-sm text-muted-foreground">{formatDate(b.scheduled_date)} at {formatTime(b.start_time)} · {b.reference}</p>
                  <p className="text-sm text-muted-foreground">{currency(b.total_price)}{b.cleaner?.name ? ` · ${b.cleaner.name}` : ""}</p>
                </div>
                <Button asChild variant="outline" size="sm"><Link href={`/account/bookings/${b.id}`}>Details</Link></Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
