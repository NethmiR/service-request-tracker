// Wraps the service request endpoints for creating a request, listing the user's requests and viewing one request.
// Unwraps the resource envelope everywhere except the paginated list, which keeps its links and meta.

import { ApiEndpoint, serviceRequestEndpoint } from "@/constants/api-endpoints";
import { PAGE_QUERY_PARAMETER } from "@/constants/http";
import { BaseApiService } from "@/services/shared/base-api-service";
import type { CreateServiceRequestRequest } from "@/types/service-requests/create-service-request-request";
import type { ServiceRequestDetail } from "@/types/service-requests/service-request-detail";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";
import type { ApiResourceResponse } from "@/types/shared/api-resource-response";
import type { PaginatedResponse } from "@/types/shared/paginated-response";

export class ServiceRequestService extends BaseApiService {
  async createServiceRequest(request: CreateServiceRequestRequest): Promise<ServiceRequestDetail> {
    const response = await this.post<
      ApiResourceResponse<ServiceRequestDetail>,
      CreateServiceRequestRequest
    >(ApiEndpoint.ServiceRequests, request);

    return response.data;
  }

  async listMyServiceRequests(page: number): Promise<PaginatedResponse<ServiceRequestSummary>> {
    return this.get<PaginatedResponse<ServiceRequestSummary>>(ApiEndpoint.MyServiceRequests, {
      params: { [PAGE_QUERY_PARAMETER]: page },
    });
  }

  async getServiceRequest(serviceRequestId: number): Promise<ServiceRequestDetail> {
    const response = await this.get<ApiResourceResponse<ServiceRequestDetail>>(
      serviceRequestEndpoint(serviceRequestId),
    );

    return response.data;
  }
}

export const serviceRequestService = new ServiceRequestService();
