// Wraps the overview endpoint that returns the count boxes, highlight lists and chart datasets in one response.
// Unwraps the resource envelope so callers receive the overview itself.

import { ApiEndpoint } from "@/constants/api-endpoints";
import { BaseApiService } from "@/services/shared/base-api-service";
import type { Overview } from "@/types/overview/overview";
import type { ApiResourceResponse } from "@/types/shared/api-resource-response";

export class OverviewService extends BaseApiService {
  async getOverview(): Promise<Overview> {
    const response = await this.get<ApiResourceResponse<Overview>>(ApiEndpoint.Overview);

    return response.data;
  }
}

export const overviewService = new OverviewService();
