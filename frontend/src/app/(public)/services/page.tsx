"use client";

import { LoadingCards } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { usePublicSettings } from "@/hooks/use-settings";
import { apiGet } from "@/lib/api";
import { formatCurrency } from "@/lib/format";
import type { Service } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { Clock, Sparkles } from "lucide-react";
import Link from "next/link";

export default function ServicesPage() {
  const { data: settings } = usePublicSettings();
  const { data, isLoading } = useQuery({ queryKey: ["public", "services"], queryFn: () => apiGet<Service[]>("public/services") });
  const currency = settings?.currency ?? "NZD";

  return (
    <div className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
      <h1 className="text-3xl font-semibold tracking-tight">Our cleaning services</h1>
      <p className="mt-2 max-w-2xl text-muted-foreground">Transparent hourly pricing. Vetted professionals. Book any service in minutes.</p>

      <div className="mt-10">
        {isLoading ? (
          <LoadingCards count={6} />
        ) : (
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {data?.map((s) => (
              <Card key={s.id} className="flex flex-col rounded-2xl transition-shadow hover:shadow-md">
                <CardContent className="flex flex-1 flex-col p-6">
                  <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary"><Sparkles className="h-5 w-5" /></div>
                  <h3 className="text-lg font-semibold">{s.name}</h3>
                  <p className="mt-1 flex-1 text-sm text-muted-foreground">{s.short_description}</p>
                  <div className="mt-4 flex items-center gap-4 text-sm text-muted-foreground">
                    <span className="font-semibold text-primary">{formatCurrency(s.price_per_hour, currency)}/h</span>
                    <span className="flex items-center gap-1"><Clock className="h-3.5 w-3.5" /> ~{s.default_duration_hours}h</span>
                  </div>
                  <div className="mt-5 flex gap-2">
                    <Button asChild className="flex-1"><Link href={`/book?service=${s.slug}`}>Book</Link></Button>
                    <Button asChild variant="outline"><Link href={`/services/${s.slug}`}>Details</Link></Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
