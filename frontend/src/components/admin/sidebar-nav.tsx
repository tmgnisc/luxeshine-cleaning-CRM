"use client";

import { ADMIN_NAV } from "@/components/admin/nav-items";
import { cn } from "@/lib/utils";
import { useAuth } from "@/providers/auth-provider";
import { ExternalLink } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";

export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();
  const { user } = useAuth();
  const isSuperAdmin = user?.role === "super_admin";

  return (
    <div className="flex h-full flex-col bg-sidebar text-sidebar-foreground">
      <div className="flex items-center px-5 py-5">
        <span className="flex items-center rounded-xl bg-white px-3 py-2 shadow-sm">
          <Image src="/logo.png" alt="LuxeShine Cleaning" width={500} height={235} priority className="h-8 w-auto" />
        </span>
      </div>

      <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-2">
        {ADMIN_NAV.filter((item) => !item.superAdminOnly || isSuperAdmin).map((item) => {
          const active = item.exact ? pathname === item.href : pathname.startsWith(item.href);
          return (
            <Link
              key={item.href}
              href={item.href}
              onClick={onNavigate}
              className={cn(
                "flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors",
                active
                  ? "bg-sidebar-accent text-sidebar-accent-foreground shadow-sm"
                  : "text-sidebar-foreground/80 hover:bg-white/5 hover:text-white",
              )}
            >
              <item.icon className="h-[18px] w-[18px]" />
              {item.label}
            </Link>
          );
        })}
      </nav>

      <div className="border-t border-sidebar-border px-3 py-3">
        <Link
          href="/"
          className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-sidebar-muted transition-colors hover:bg-white/5 hover:text-white"
        >
          <ExternalLink className="h-4 w-4" />
          View public website
        </Link>
      </div>
    </div>
  );
}
