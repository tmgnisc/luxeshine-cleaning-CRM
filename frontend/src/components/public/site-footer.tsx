"use client";

import { usePublicSettings } from "@/hooks/use-settings";
import Image from "next/image";
import Link from "next/link";

export function SiteFooter() {
  const { data: s } = usePublicSettings();

  return (
    <footer className="border-t border-border/60 bg-sidebar text-sidebar-foreground">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
        <div className="md:col-span-2">
          <div className="flex items-center">
            <span className="flex items-center rounded-xl bg-white px-3 py-2 shadow-sm">
              <Image src="/logo.png" alt={s?.site_title ?? "LuxeShine Cleaning"} width={500} height={235} className="h-8 w-auto" />
            </span>
          </div>
          <p className="mt-3 max-w-sm text-sm text-sidebar-muted">{s?.tagline}</p>
        </div>
        <div>
          <h4 className="mb-3 text-sm font-semibold text-white">Company</h4>
          <ul className="space-y-2 text-sm text-sidebar-muted">
            <li><Link href="/services" className="hover:text-white">Services</Link></li>
            <li><Link href="/book" className="hover:text-white">Book a cleaning</Link></li>
            <li><Link href="/contact" className="hover:text-white">Contact</Link></li>
          </ul>
        </div>
        <div>
          <h4 className="mb-3 text-sm font-semibold text-white">Get in touch</h4>
          <ul className="space-y-2 text-sm text-sidebar-muted">
            {s?.support_phone ? <li>{s.support_phone}</li> : null}
            {s?.support_email ? <li>{s.support_email}</li> : null}
            {s?.office_address ? <li>{s.office_address}</li> : null}
          </ul>
        </div>
      </div>
      <div className="border-t border-sidebar-border py-4 text-center text-xs text-sidebar-muted">
        © {new Date().getFullYear()} {s?.site_title ?? "LuxeShine Cleaning"}. All rights reserved.
      </div>
    </footer>
  );
}
