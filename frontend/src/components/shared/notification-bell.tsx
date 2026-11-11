"use client";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useMarkNotifications, useNotifications, useUnreadCount } from "@/hooks/use-notifications";
import { formatDateTime } from "@/lib/format";
import { cn } from "@/lib/utils";
import { Bell } from "lucide-react";

export function NotificationBell() {
  const { data: count } = useUnreadCount();
  const { data: list } = useNotifications();
  const { markOne, markAll } = useMarkNotifications();
  const unread = count?.unread ?? 0;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative rounded-full">
          <Bell className="h-5 w-5" />
          {unread > 0 && (
            <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-destructive-foreground">
              {unread > 9 ? "9+" : unread}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80 p-0">
        <div className="flex items-center justify-between border-b px-4 py-2.5">
          <span className="text-sm font-semibold">Notifications</span>
          {unread > 0 && (
            <button
              className="text-xs text-primary hover:underline"
              onClick={() => markAll.mutate()}
            >
              Mark all read
            </button>
          )}
        </div>
        <div className="max-h-96 overflow-y-auto">
          {!list || list.data.length === 0 ? (
            <p className="px-4 py-8 text-center text-sm text-muted-foreground">No notifications yet.</p>
          ) : (
            list.data.map((n) => (
              <button
                key={n.id}
                onClick={() => !n.read && markOne.mutate(n.id)}
                className={cn(
                  "flex w-full flex-col gap-0.5 border-b px-4 py-3 text-left last:border-0 hover:bg-muted/50",
                  !n.read && "bg-primary/5",
                )}
              >
                <span className="text-sm font-medium text-foreground">{n.data.title}</span>
                <span className="line-clamp-2 text-xs text-muted-foreground">{n.data.message}</span>
                <span className="text-[11px] text-muted-foreground/70">{formatDateTime(n.created_at)}</span>
              </button>
            ))
          )}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
