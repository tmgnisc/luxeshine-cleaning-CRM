"use client";

import { StarRating } from "@/components/shared/star-rating";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { usePublicSettings } from "@/hooks/use-settings";
import { apiGet, apiGetPage } from "@/lib/api";
import { formatCurrency } from "@/lib/format";
import type { PublicReview, Service } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { CalendarCheck, MapPin, ShieldCheck, Sparkles, Clock } from "lucide-react";
import Link from "next/link";

export default function HomePage() {
  const { data: settings } = usePublicSettings();
  const services = useQuery({ queryKey: ["public", "services"], queryFn: () => apiGet<Service[]>("public/services") });
  const reviews = useQuery({ queryKey: ["public", "reviews"], queryFn: () => apiGetPage<PublicReview>("public/reviews", { params: { per_page: 6 } }) });
  const currency = settings?.currency ?? "NZD";

  return (
    <div>
      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24">
          <div>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
              <Sparkles className="h-3.5 w-3.5" /> Trusted cleaners across Dubai
            </span>
            <h1 className="mt-4 text-4xl font-semibold tracking-tight text-foreground sm:text-5xl">
              {settings?.hero_headline ?? "A spotless home, without the hassle"}
            </h1>
            <p className="mt-4 max-w-md text-lg text-muted-foreground">
              {settings?.hero_subline ?? "Book trusted, vetted cleaners in just a few taps."}
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Button asChild size="lg"><Link href="/book">Book a cleaning</Link></Button>
              <Button asChild size="lg" variant="outline"><Link href="/services">Browse services</Link></Button>
            </div>
            <div className="mt-8 flex flex-wrap gap-6 text-sm text-muted-foreground">
              <span className="flex items-center gap-2"><ShieldCheck className="h-4 w-4 text-primary" /> Vetted & insured</span>
              <span className="flex items-center gap-2"><Clock className="h-4 w-4 text-primary" /> Same-week availability</span>
            </div>
          </div>
          <div className="relative">
            <div className="aspect-square rounded-3xl bg-gradient-to-br from-primary/15 via-primary/5 to-transparent p-8">
              <div className="flex h-full flex-col justify-center gap-4">
                {services.data?.slice(0, 3).map((s) => (
                  <Card key={s.id} className="rounded-2xl shadow-sm">
                    <CardContent className="flex items-center justify-between p-4">
                      <div>
                        <p className="font-medium">{s.name}</p>
                        <p className="text-xs text-muted-foreground">{s.short_description}</p>
                      </div>
                      <span className="shrink-0 rounded-lg bg-primary/10 px-2.5 py-1 text-sm font-semibold text-primary">
                        {formatCurrency(s.price_per_hour, currency)}/h
                      </span>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Services */}
      <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 className="text-2xl font-semibold tracking-tight">Our services</h2>
        <p className="mt-1 text-muted-foreground">Professional cleaning for every corner of your home.</p>
        <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {services.data?.map((s) => (
            <Card key={s.id} className="group rounded-2xl transition-shadow hover:shadow-md">
              <CardContent className="p-5">
                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary"><Sparkles className="h-5 w-5" /></div>
                <h3 className="font-semibold">{s.name}</h3>
                <p className="mt-1 text-sm text-muted-foreground">{s.short_description}</p>
                <div className="mt-4 flex items-center justify-between">
                  <span className="text-sm font-semibold text-primary">{formatCurrency(s.price_per_hour, currency)}/h</span>
                  <Button asChild size="sm" variant="ghost"><Link href={`/services/${s.slug}`}>Learn more →</Link></Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </section>

      {/* How it works */}
      <section id="how" className="bg-card/60 py-16">
        <div className="mx-auto max-w-6xl px-4 sm:px-6">
          <h2 className="text-center text-2xl font-semibold tracking-tight">How it works</h2>
          <div className="mt-10 grid gap-8 sm:grid-cols-3">
            {[
              { icon: Sparkles, title: "Choose a service", text: "Pick what you need and how long you need it for." },
              { icon: CalendarCheck, title: "Pick a time", text: "Select a date and time slot that suits you." },
              { icon: ShieldCheck, title: "Relax", text: "A vetted cleaner arrives and does the work." },
            ].map((step, i) => (
              <div key={step.title} className="text-center">
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground"><step.icon className="h-6 w-6" /></div>
                <h3 className="font-semibold">{i + 1}. {step.title}</h3>
                <p className="mt-1 text-sm text-muted-foreground">{step.text}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Reviews */}
      {reviews.data && reviews.data.data.length > 0 ? (
        <section id="reviews" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
          <h2 className="text-2xl font-semibold tracking-tight">What our customers say</h2>
          <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {reviews.data.data.map((r) => (
              <Card key={r.id} className="rounded-2xl">
                <CardContent className="p-5">
                  <StarRating value={r.rating} size={16} />
                  <p className="mt-3 text-sm text-foreground">“{r.comment}”</p>
                  <p className="mt-4 text-xs font-medium text-muted-foreground">
                    {r.customer_first_name}{r.service ? ` · ${r.service}` : ""}
                  </p>
                </CardContent>
              </Card>
            ))}
          </div>
        </section>
      ) : null}

      {/* Service areas */}
      {settings?.service_areas && settings.service_areas.length > 0 ? (
        <section className="bg-card/60 py-14">
          <div className="mx-auto max-w-6xl px-4 text-center sm:px-6">
            <h2 className="flex items-center justify-center gap-2 text-2xl font-semibold"><MapPin className="h-5 w-5 text-primary" /> Where we clean</h2>
            <div className="mt-6 flex flex-wrap justify-center gap-2">
              {settings.service_areas.map((area) => (
                <span key={area} className="rounded-full bg-background px-4 py-1.5 text-sm text-foreground shadow-sm">{area}</span>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {/* CTA strip */}
      <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div className="flex flex-col items-center justify-between gap-4 rounded-3xl bg-primary px-8 py-10 text-center text-primary-foreground sm:flex-row sm:text-left">
          <div>
            <h2 className="text-2xl font-semibold">Ready for a cleaner home?</h2>
            <p className="mt-1 text-primary-foreground/80">Book in minutes — no account required to browse.</p>
          </div>
          <Button asChild size="lg" variant="secondary"><Link href="/book">Book now</Link></Button>
        </div>
      </section>
    </div>
  );
}
