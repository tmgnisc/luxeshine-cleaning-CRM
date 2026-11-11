"use client";

import { SiteFooter } from "@/components/public/site-footer";
import { SiteHeader } from "@/components/public/site-header";
import { usePublicSettings } from "@/hooks/use-settings";
import type { ReactNode } from "react";

export default function PublicLayout({ children }: { children: ReactNode }) {
  const { data: settings } = usePublicSettings();

  return (
    <div className="flex min-h-screen flex-col">
      {settings?.maintenance_mode ? (
        <div className="bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950">
          {settings.maintenance_message ?? "We are briefly down for maintenance."}
        </div>
      ) : null}
      <SiteHeader />
      <main className="flex-1">{children}</main>
      <SiteFooter />
    </div>
  );
}
