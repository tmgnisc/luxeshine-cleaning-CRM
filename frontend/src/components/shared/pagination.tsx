"use client";

import { Button } from "@/components/ui/button";
import type { PaginationMeta } from "@/lib/api";
import { ChevronLeft, ChevronRight } from "lucide-react";

export function Pagination({
  meta,
  onPage,
}: {
  meta?: PaginationMeta;
  onPage: (page: number) => void;
}) {
  if (!meta || meta.total === 0) return null;

  return (
    <div className="flex items-center justify-between gap-4 pt-2 text-sm text-muted-foreground">
      <span>
        {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
      </span>
      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
        >
          <ChevronLeft className="h-4 w-4" />
          Prev
        </Button>
        <span className="tabular-nums">
          {meta.current_page} / {meta.last_page}
        </span>
        <Button
          variant="outline"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Next
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
