"use client";

import { StarRating } from "@/components/shared/star-rating";
import { Pagination } from "@/components/shared/pagination";
import { EmptyState, LoadingRows } from "@/components/shared/states";
import { Card, CardContent } from "@/components/ui/card";
import { apiGetPage } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { Review } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { Star } from "lucide-react";
import { useState } from "react";

export default function CleanerReviewsPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading } = useQuery({ queryKey: ["cleaner", "reviews", page], queryFn: () => apiGetPage<Review>("cleaner/reviews", { params: { page, per_page: 15 } }) });

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold tracking-tight">My reviews</h1>
      {isLoading ? (
        <LoadingRows />
      ) : data && data.data.length === 0 ? (
        <EmptyState title="No reviews yet" description="Published customer reviews will appear here." icon={Star} />
      ) : (
        <div className="space-y-3">
          {data?.data.map((r) => (
            <Card key={r.id} className="rounded-2xl">
              <CardContent className="p-5">
                <div className="flex items-center justify-between">
                  <StarRating value={r.rating} size={16} />
                  <span className="text-xs text-muted-foreground">{formatDate(r.created_at)}</span>
                </div>
                <p className="mt-2 text-sm">{r.comment}</p>
                {r.service_name ? <p className="mt-1 text-xs text-muted-foreground">{r.service_name}</p> : null}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
      <Pagination meta={data?.meta} onPage={setPage} />
    </div>
  );
}
