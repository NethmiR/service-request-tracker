// Previous and next page controls with a page indicator.
// Driven by the meta block of a paginated API response and reports changes through onPageChange.

"use client";

import { Button } from "@/components/shared/button";
import { UiMessage, formatPageIndicator } from "@/constants/ui-messages";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";
import type { PaginationMeta } from "@/types/shared/paginated-response";

interface PaginationProps {
  meta: PaginationMeta;
  onPageChange: (page: number) => void;
  disabled?: boolean;
  className?: string;
}

export function Pagination({ meta, onPageChange, disabled = false, className }: PaginationProps) {
  if (meta.last_page <= 1) {
    return null;
  }

  const isFirstPage = meta.current_page <= 1;
  const isLastPage = meta.current_page >= meta.last_page;

  return (
    <nav
      aria-label={UiMessage.Pagination}
      className={mergeClassNames("flex items-center justify-center gap-3", className)}
    >
      <Button
        variant={ButtonVariant.Ghost}
        size={ComponentSize.Small}
        disabled={disabled || isFirstPage}
        onClick={() => onPageChange(meta.current_page - 1)}
      >
        <span aria-hidden="true">&lsaquo;</span>
        {UiMessage.PreviousPage}
      </Button>
      <p className="text-xs text-muted">
        {formatPageIndicator(meta.current_page, meta.last_page)}
      </p>
      <Button
        variant={ButtonVariant.Ghost}
        size={ComponentSize.Small}
        disabled={disabled || isLastPage}
        onClick={() => onPageChange(meta.current_page + 1)}
      >
        {UiMessage.NextPage}
        <span aria-hidden="true">&rsaquo;</span>
      </Button>
    </nav>
  );
}
