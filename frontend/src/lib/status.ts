import type { BookingStatus, VerificationStatus } from "@/lib/types";

type Tone = {
  label: string;
  className: string;
};

export const BOOKING_STATUS: Record<BookingStatus, Tone> = {
  pending: { label: "Pending", className: "bg-amber-100 text-amber-800 border-amber-200" },
  confirmed: { label: "Confirmed", className: "bg-green-100 text-green-800 border-green-200" },
  in_progress: { label: "In progress", className: "bg-blue-100 text-blue-800 border-blue-200" },
  completed: { label: "Completed", className: "bg-teal-100 text-teal-800 border-teal-200" },
  cancelled: { label: "Cancelled", className: "bg-red-100 text-red-700 border-red-200" },
};

export const VERIFICATION_STATUS: Record<VerificationStatus, Tone> = {
  verified: { label: "Verified", className: "bg-green-100 text-green-800 border-green-200" },
  pending: { label: "Pending", className: "bg-amber-100 text-amber-800 border-amber-200" },
  rejected: { label: "Rejected", className: "bg-red-100 text-red-700 border-red-200" },
};

/** Valid next statuses an admin may move a booking to (mirrors the backend machine). */
export const NEXT_STATUSES: Record<BookingStatus, BookingStatus[]> = {
  pending: ["confirmed", "cancelled"],
  confirmed: ["in_progress", "cancelled"],
  in_progress: ["completed"],
  completed: [],
  cancelled: [],
};

export const DAY_LABELS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
