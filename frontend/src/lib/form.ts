import { ApiError } from "@/lib/api";
import type { FieldValues, Path, UseFormSetError } from "react-hook-form";
import { toast } from "sonner";

/**
 * Map a backend 422 response onto react-hook-form fields; otherwise toast the
 * message. Returns true if field-level errors were applied.
 */
export function applyApiErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
): boolean {
  if (error instanceof ApiError && error.errors) {
    let applied = false;
    for (const [field, messages] of Object.entries(error.errors)) {
      setError(field as Path<T>, { type: "server", message: messages[0] });
      applied = true;
    }
    if (applied) return true;
  }

  toast.error(error instanceof ApiError ? error.message : "Something went wrong.");
  return false;
}
