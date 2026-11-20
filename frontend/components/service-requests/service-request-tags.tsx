// List of neutral badges, one per tag of a service request, in the order the API returns them.
// Shared by the request lists and the request detail page.

import { Badge } from "@/components/shared/badge";
import { SERVICE_REQUEST_TAG_LABELS } from "@/constants/enum-labels";
import type { ServiceRequestTag } from "@/enums/service-request-tag";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface ServiceRequestTagsProps {
  tags: readonly ServiceRequestTag[];
  className?: string;
}

export function ServiceRequestTags({ tags, className }: ServiceRequestTagsProps) {
  return (
    <ul className={mergeClassNames("flex flex-wrap gap-1.5", className)}>
      {tags.map((tag) => (
        <li key={tag}>
          <Badge>{SERVICE_REQUEST_TAG_LABELS[tag]}</Badge>
        </li>
      ))}
    </ul>
  );
}
