"use client";

import { apiGet, apiGetPage, apiSend } from "@/lib/api";
import type { AppNotification } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

export function useUnreadCount() {
  return useQuery({
    queryKey: ["notifications", "unread-count"],
    queryFn: () => apiGet<{ unread: number }>("notifications/unread-count"),
    refetchInterval: 60_000,
  });
}

export function useNotifications() {
  return useQuery({
    queryKey: ["notifications", "list"],
    queryFn: () => apiGetPage<AppNotification>("notifications", { params: { per_page: 15 } }),
  });
}

export function useMarkNotifications() {
  const qc = useQueryClient();
  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["notifications"] });
  };

  const markOne = useMutation({
    mutationFn: (id: string) => apiSend("post", `notifications/${id}/read`),
    onSuccess: invalidate,
  });

  const markAll = useMutation({
    mutationFn: () => apiSend("post", "notifications/read-all"),
    onSuccess: invalidate,
  });

  return { markOne, markAll };
}
