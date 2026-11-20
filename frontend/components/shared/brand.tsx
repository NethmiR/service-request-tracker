// Brand block with the initials mark, the organization name and the product name.
// Shown on the sign in page and at the top of the signed in layout.

import { BRAND_INITIALS, ORGANIZATION_NAME, PRODUCT_NAME } from "@/constants/application";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface BrandProps {
  className?: string;
}

export function Brand({ className }: BrandProps) {
  return (
    <div className={mergeClassNames("flex items-center gap-3", className)}>
      <span
        aria-hidden="true"
        className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold tracking-wide text-primary-foreground"
      >
        {BRAND_INITIALS}
      </span>
      <div className="flex flex-col leading-tight">
        <span className="text-base font-bold text-foreground">{ORGANIZATION_NAME}</span>
        <span className="text-sm text-muted">{PRODUCT_NAME}</span>
      </div>
    </div>
  );
}
