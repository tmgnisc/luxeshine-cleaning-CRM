"use client";

import { NotificationBell } from "@/components/shared/notification-bell";
import { UserMenu } from "@/components/shared/user-menu";
import { cn } from "@/lib/utils";
import { type LucideIcon } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";

export interface PortalNavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  exact?: boolean;
}

export function PortalShell({
  nav,
  profileHref,
  children,
}: {
  nav: PortalNavItem[];
  profileHref?: string;
  children: ReactNode;
}) {
  const pathname = usePathname();

  return (
    <div className="flex min-h-screen flex-col bg-background">
      <header className="sticky top-0 z-40 border-b border-border/60 bg-background/85 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
          <Link href="/" className="flex items-center font-semibold" aria-label="LuxeShine Cleaning">
            <Image src="/logo.png" alt="LuxeShine Cleaning" width={500} height={235} priority className="h-9 w-auto" />
          </Link>
          <nav className="flex flex-1 items-center gap-1 overflow-x-auto">
            {nav.map((item) => {
              const active = item.exact ? pathname === item.href : pathname.startsWith(item.href);
              return (
                <Link key={item.href} href={item.href}
                  className={cn("flex items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1.5 text-sm font-medium transition-colors",
                    active ? "bg-primary/10 text-primary" : "text-muted-foreground hover:text-foreground")}>
                  <item.icon className="h-4 w-4" />
                  <span className="hidden sm:inline">{item.label}</span>
                </Link>
              );
            })}
          </nav>
          <div className="flex items-center gap-1">
            <NotificationBell />
            <UserMenu profileHref={profileHref} />
          </div>
        </div>
      </header>
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-6 sm:px-6">{children}</main>
    </div>
  );
}
