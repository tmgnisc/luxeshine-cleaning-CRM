"use client";

import { PortalShell } from "@/components/portal/portal-shell";
import { CalendarClock, Home, MapPin, User } from "lucide-react";
import type { ReactNode } from "react";

export default function AccountLayout({ children }: { children: ReactNode }) {
  return (
    <PortalShell
      profileHref="/account/profile"
      nav={[
        { label: "Overview", href: "/account", icon: Home, exact: true },
        { label: "Bookings", href: "/account/bookings", icon: CalendarClock },
        { label: "Addresses", href: "/account/addresses", icon: MapPin },
        { label: "Profile", href: "/account/profile", icon: User },
      ]}
    >
      {children}
    </PortalShell>
  );
}
