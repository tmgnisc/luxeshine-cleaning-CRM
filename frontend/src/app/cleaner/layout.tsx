"use client";

import { PortalShell } from "@/components/portal/portal-shell";
import { CalendarClock, LayoutDashboard, Star, User } from "lucide-react";
import type { ReactNode } from "react";

export default function CleanerLayout({ children }: { children: ReactNode }) {
  return (
    <PortalShell
      profileHref="/cleaner/profile"
      nav={[
        { label: "Dashboard", href: "/cleaner", icon: LayoutDashboard, exact: true },
        { label: "Jobs", href: "/cleaner/jobs", icon: CalendarClock },
        { label: "Availability", href: "/cleaner/availability", icon: CalendarClock },
        { label: "Reviews", href: "/cleaner/reviews", icon: Star },
        { label: "Profile", href: "/cleaner/profile", icon: User },
      ]}
    >
      {children}
    </PortalShell>
  );
}
