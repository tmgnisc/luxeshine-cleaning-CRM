"use client";

import { LoadingCards } from "@/components/shared/states";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { usePublicSettings } from "@/hooks/use-settings";
import { apiGet, ApiError } from "@/lib/api";
import { formatCurrency } from "@/lib/format";
import type { Service } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Clock, Sparkles } from "lucide-react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { use } from "react";

export default function ServiceDetailPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const { data: settings } = usePublicSettings();
  const currency = settings?.currency ?? "NZD";

  const { data, isLoading, error } = useQuery({
    queryKey: ["public", "service", slug],
    queryFn: () => apiGet<Service>(`public/services/${slug}`),
    retry: false,
  });

  if (error instanceof ApiError && error.status === 404) notFound();
  if (isLoading || !data) return <div className="mx-auto max-w-3xl px-4 py-14"><LoadingCards count={1} /></div>;

  return (
    <div className="mx-auto max-w-3xl px-4 py-14 sm:px-6">
      <Button asChild variant="ghost" size="sm" className="-ml-2 mb-4"><Link href="/services"><ArrowLeft className="h-4 w-4" /> All services</Link></Button>

      <div className="flex items-start gap-4">
        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary"><Sparkles className="h-6 w-6" /></div>
        <div>
          <h1 className="text-3xl font-semibold tracking-tight">{data.name}</h1>
          <p className="mt-1 text-muted-foreground">{data.short_description}</p>
        </div>
      </div>

      <div className="mt-6 flex flex-wrap gap-3">
        <Card className="rounded-xl"><CardContent className="px-4 py-3"><p className="text-xs text-muted-foreground">Price</p><p className="font-semibold text-primary">{formatCurrency(data.price_per_hour, currency)}/hour</p></CardContent></Card>
        <Card className="rounded-xl"><CardContent className="flex items-center gap-2 px-4 py-3"><Clock className="h-4 w-4 text-muted-foreground" /><div><p className="text-xs text-muted-foreground">Typical duration</p><p className="font-semibold">{data.default_duration_hours} hours</p></div></CardContent></Card>
      </div>

      {data.description ? (
        <div className="prose prose-sm mt-8 max-w-none text-foreground">
          {data.description.split("\n\n").map((p, i) => <p key={i} className="mb-4 text-muted-foreground">{p}</p>)}
        </div>
      ) : null}

      <div className="mt-8">
        <Button asChild size="lg"><Link href={`/book?service=${data.slug}`}>Book {data.name}</Link></Button>
      </div>
    </div>
  );
}
