"use client";

import { Button } from "@/components/ui/button";
import { usePublicSettings } from "@/hooks/use-settings";
import { homePathForRole, useAuth } from "@/providers/auth-provider";
import { Menu, X } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { useState } from "react";

const LINKS = [
  { label: "Services", href: "/services" },
  { label: "How it works", href: "/#how" },
  { label: "Reviews", href: "/#reviews" },
  { label: "Contact", href: "/contact" },
];

export function SiteHeader() {
  const { data: settings } = usePublicSettings();
  const { user } = useAuth();
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 border-b border-border/60 bg-background/85 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <Link href="/" className="flex items-center font-semibold" aria-label={settings?.site_title ?? "LuxeShine Cleaning"}>
          <Image src="/logo.png" alt={settings?.site_title ?? "LuxeShine Cleaning"} width={500} height={235} priority className="h-9 w-auto" />
        </Link>

        <nav className="hidden items-center gap-7 text-sm font-medium text-muted-foreground md:flex">
          {LINKS.map((l) => <Link key={l.href} href={l.href} className="transition-colors hover:text-foreground">{l.label}</Link>)}
        </nav>

        <div className="hidden items-center gap-2 md:flex">
          {user ? (
            <Button asChild variant="outline"><Link href={homePathForRole(user.role)}>My account</Link></Button>
          ) : (
            <Button asChild variant="ghost"><Link href="/login">Sign in</Link></Button>
          )}
          <Button asChild><Link href="/book">Book now</Link></Button>
        </div>

        <button className="md:hidden" onClick={() => setOpen(!open)} aria-label="Menu">
          {open ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
        </button>
      </div>

      {open ? (
        <div className="border-t bg-background px-4 py-3 md:hidden">
          <nav className="flex flex-col gap-1">
            {LINKS.map((l) => (
              <Link key={l.href} href={l.href} onClick={() => setOpen(false)} className="rounded-lg px-2 py-2 text-sm hover:bg-muted">{l.label}</Link>
            ))}
            <div className="mt-2 flex gap-2">
              <Button asChild variant="outline" className="flex-1"><Link href={user ? homePathForRole(user.role) : "/login"}>{user ? "Account" : "Sign in"}</Link></Button>
              <Button asChild className="flex-1"><Link href="/book">Book now</Link></Button>
            </div>
          </nav>
        </div>
      ) : null}
    </header>
  );
}
