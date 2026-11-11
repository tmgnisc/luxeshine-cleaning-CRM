"use client";

import { cn } from "@/lib/utils";
import { Star } from "lucide-react";

export function StarRating({
  value,
  onChange,
  size = 16,
  className,
}: {
  value: number;
  onChange?: (value: number) => void;
  size?: number;
  className?: string;
}) {
  const interactive = typeof onChange === "function";

  return (
    <div className={cn("flex items-center gap-0.5", className)}>
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          disabled={!interactive}
          onClick={() => onChange?.(star)}
          className={cn(interactive ? "cursor-pointer transition-transform hover:scale-110" : "cursor-default")}
          aria-label={`${star} star${star > 1 ? "s" : ""}`}
        >
          <Star
            style={{ width: size, height: size }}
            className={cn(
              star <= Math.round(value)
                ? "fill-amber-400 text-amber-400"
                : "fill-transparent text-muted-foreground/40",
            )}
          />
        </button>
      ))}
    </div>
  );
}
