import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { BOOKING_STATUS, VERIFICATION_STATUS } from "@/lib/status";
import type { BookingStatus, VerificationStatus } from "@/lib/types";

export function BookingStatusBadge({ status }: { status: BookingStatus }) {
  const tone = BOOKING_STATUS[status];
  return (
    <Badge variant="outline" className={cn("font-medium", tone.className)}>
      {tone.label}
    </Badge>
  );
}

export function VerificationBadge({ status }: { status: VerificationStatus }) {
  const tone = VERIFICATION_STATUS[status];
  return (
    <Badge variant="outline" className={cn("font-medium", tone.className)}>
      {tone.label}
    </Badge>
  );
}
