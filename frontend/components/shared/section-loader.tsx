// Centred large spinner shown inside a page section while its content loads.
// Used by every panel and section that loads data, so the loading state looks the same everywhere.

import { Spinner } from "@/components/shared/spinner";
import { ComponentSize } from "@/enums/ui/component-size";

export function SectionLoader() {
  return (
    <div className="flex justify-center py-10 text-primary">
      <Spinner size={ComponentSize.Large} />
    </div>
  );
}
