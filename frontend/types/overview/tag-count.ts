// Number of service requests that carry one tag, mirrored from the backend TagCountResource.
// The tag is a value item, so no tag name is ever used as a key.

import type { ServiceRequestTag } from "@/enums/service-request-tag";

export interface TagCount {
  tag: ServiceRequestTag;
  count: number;
}
