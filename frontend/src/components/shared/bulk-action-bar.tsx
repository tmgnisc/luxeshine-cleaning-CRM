"use client";

import { ConfirmDialog } from "@/components/shared/confirm-dialog";
import { Button } from "@/components/ui/button";
import { Trash2, X } from "lucide-react";
import { useState } from "react";

/**
 * Floating bar shown when one or more rows are selected in an admin list.
 * Confirms before firing the bulk delete.
 */
export function BulkActionBar({
  count,
  noun,
  loading = false,
  onClear,
  onDelete,
}: {
  count: number;
  noun: string;
  loading?: boolean;
  onClear: () => void;
  onDelete: () => void;
}) {
  const [confirm, setConfirm] = useState(false);

  if (count === 0) return null;

  const label = `${count} ${noun}${count === 1 ? "" : "s"}`;

  return (
    <>
      <div className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 backdrop-blur lg:pl-64">
        <div className="mx-auto flex max-w-7xl items-center gap-3 px-6 py-3">
          <span className="text-sm font-medium">{label} selected</span>
          <Button variant="ghost" size="sm" onClick={onClear} disabled={loading}>
            <X className="h-4 w-4" /> Clear
          </Button>
          <Button
            variant="destructive"
            size="sm"
            className="ml-auto"
            onClick={() => setConfirm(true)}
            disabled={loading}
          >
            <Trash2 className="h-4 w-4" /> Delete selected
          </Button>
        </div>
      </div>

      <ConfirmDialog
        open={confirm}
        onOpenChange={setConfirm}
        title={`Delete ${label}?`}
        description="This cannot be undone. Items that can't be deleted (for example because they have bookings) are skipped and reported."
        confirmLabel="Delete"
        destructive
        loading={loading}
        onConfirm={() => {
          setConfirm(false);
          onDelete();
        }}
      />
    </>
  );
}
