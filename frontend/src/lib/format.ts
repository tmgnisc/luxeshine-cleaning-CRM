import { format, parseISO } from "date-fns";

export function formatCurrency(amount: number, currency = "NZD"): string {
  try {
    return new Intl.NumberFormat("en-NZ", {
      style: "currency",
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(amount ?? 0);
  } catch {
    const value = new Intl.NumberFormat("en-NZ", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(amount ?? 0);
    return `${currency} ${value}`;
  }
}

export function formatDate(date?: string | null, pattern = "d MMM yyyy"): string {
  if (!date) return "—";
  try {
    const d = date.includes("T") ? parseISO(date) : parseISO(`${date}T00:00:00`);
    return format(d, pattern);
  } catch {
    return date;
  }
}

export function formatTime(time?: string | null): string {
  if (!time) return "—";
  const [h, m] = time.split(":");
  const hour = Number(h);
  const suffix = hour >= 12 ? "PM" : "AM";
  const twelve = hour % 12 === 0 ? 12 : hour % 12;
  return `${twelve}:${m ?? "00"} ${suffix}`;
}

export function formatDateTime(date?: string | null): string {
  if (!date) return "—";
  try {
    return format(parseISO(date), "d MMM yyyy, h:mm a");
  } catch {
    return date;
  }
}
