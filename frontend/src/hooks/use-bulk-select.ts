"use client";

import { useCallback, useMemo, useState } from "react";

/**
 * Tracks a set of selected row ids for list tables with multi-select + bulk
 * actions. `currentIds` is the ids visible on the current page, used for the
 * header "select all" checkbox.
 */
export function useBulkSelect(currentIds: number[]) {
  const [selected, setSelected] = useState<Set<number>>(new Set());

  const toggle = useCallback((id: number) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const clear = useCallback(() => setSelected(new Set()), []);

  const toggleAll = useCallback(() => {
    setSelected((prev) => {
      const allOnPage = currentIds.length > 0 && currentIds.every((id) => prev.has(id));
      const next = new Set(prev);
      if (allOnPage) {
        currentIds.forEach((id) => next.delete(id));
      } else {
        currentIds.forEach((id) => next.add(id));
      }
      return next;
    });
  }, [currentIds]);

  const allOnPageSelected = useMemo(
    () => currentIds.length > 0 && currentIds.every((id) => selected.has(id)),
    [currentIds, selected],
  );

  const someOnPageSelected = useMemo(
    () => currentIds.some((id) => selected.has(id)) && !allOnPageSelected,
    [currentIds, selected, allOnPageSelected],
  );

  return {
    selected,
    ids: useMemo(() => Array.from(selected), [selected]),
    count: selected.size,
    isSelected: useCallback((id: number) => selected.has(id), [selected]),
    toggle,
    toggleAll,
    clear,
    allOnPageSelected,
    someOnPageSelected,
  };
}
