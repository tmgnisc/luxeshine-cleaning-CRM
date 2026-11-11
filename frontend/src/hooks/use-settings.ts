"use client";

import { apiGet } from "@/lib/api";
import { formatCurrency } from "@/lib/format";
import type { PublicSettings } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";

export function usePublicSettings() {
  return useQuery({
    queryKey: ["public", "settings"],
    queryFn: () => apiGet<PublicSettings>("public/settings"),
    staleTime: 5 * 60_000,
  });
}

export function useCurrency() {
  const { data } = usePublicSettings();
  const currency = data?.currency ?? "NZD";
  return (amount: number) => formatCurrency(amount, currency);
}
