"use client";

import { Input } from "@/components/ui/input";
import { apiGet } from "@/lib/api";
import { useQuery } from "@tanstack/react-query";
import { Search } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";

interface SearchResults {
  bookings: Array<{ id: number; reference: string; customer: string; scheduled_date: string; status: string }>;
  customers: Array<{ id: number; name: string; email: string }>;
  cleaners: Array<{ id: number; name: string; verification_status: string }>;
}

export function GlobalSearch() {
  const [term, setTerm] = useState("");
  const [debounced, setDebounced] = useState("");
  const [open, setOpen] = useState(false);
  const router = useRouter();
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const t = setTimeout(() => setDebounced(term), 300);
    return () => clearTimeout(t);
  }, [term]);

  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, []);

  const { data } = useQuery({
    queryKey: ["admin", "search", debounced],
    queryFn: () => apiGet<SearchResults>("admin/search", { params: { q: debounced } }),
    enabled: debounced.length >= 2,
  });

  const go = (href: string) => {
    setOpen(false);
    setTerm("");
    router.push(href);
  };

  const hasResults =
    data && (data.bookings.length > 0 || data.customers.length > 0 || data.cleaners.length > 0);

  return (
    <div ref={ref} className="relative w-full max-w-md">
      <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        value={term}
        onChange={(e) => {
          setTerm(e.target.value);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        placeholder="Search bookings, customers, staff…"
        className="pl-9"
      />
      {open && debounced.length >= 2 && (
        <div className="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border bg-popover shadow-lg">
          {!hasResults ? (
            <p className="px-4 py-6 text-center text-sm text-muted-foreground">No matches found.</p>
          ) : (
            <div className="max-h-96 overflow-y-auto py-1 text-sm">
              {data!.bookings.length > 0 && (
                <Group title="Bookings">
                  {data!.bookings.map((b) => (
                    <Row key={`b${b.id}`} onClick={() => go(`/admin/bookings?focus=${b.id}`)}>
                      <span className="font-medium">{b.reference}</span>
                      <span className="text-muted-foreground">{b.customer}</span>
                    </Row>
                  ))}
                </Group>
              )}
              {data!.customers.length > 0 && (
                <Group title="Customers">
                  {data!.customers.map((c) => (
                    <Row key={`c${c.id}`} onClick={() => go(`/admin/customers?focus=${c.id}`)}>
                      <span className="font-medium">{c.name}</span>
                      <span className="text-muted-foreground">{c.email}</span>
                    </Row>
                  ))}
                </Group>
              )}
              {data!.cleaners.length > 0 && (
                <Group title="Staff">
                  {data!.cleaners.map((c) => (
                    <Row key={`cl${c.id}`} onClick={() => go(`/admin/staff/${c.id}`)}>
                      <span className="font-medium">{c.name}</span>
                      <span className="text-muted-foreground">{c.verification_status}</span>
                    </Row>
                  ))}
                </Group>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function Group({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="py-1">
      <p className="px-4 py-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{title}</p>
      {children}
    </div>
  );
}

function Row({ children, onClick }: { children: React.ReactNode; onClick: () => void }) {
  return (
    <button onClick={onClick} className="flex w-full items-center justify-between gap-3 px-4 py-2 hover:bg-muted/60">
      {children}
    </button>
  );
}
