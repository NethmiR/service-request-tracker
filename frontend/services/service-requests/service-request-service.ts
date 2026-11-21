// Wraps the service request endpoints for creating, listing and viewing requests and for the support workflow.
// The workflow covers the assignee choices, self assignment, reassignment and status changes.
// Unwraps the resource envelope everywhere except the paginated lists, which keep their links and meta.

import {
  ApiEndpoint,
  serviceRequestAssignmentEndpoint,
  serviceRequestEndpoint,
  serviceRequestReassignmentEndpoint,
  serviceRequestStatusEndpoint,
} from "@/constants/api-endpoints";
import { PAGE_QUERY_PARAMETER } from "@/constants/http";
import { BaseApiService } from "@/services/shared/base-api-service";
import type { AssignServiceRequestRequest } from "@/types/service-requests/assign-service-request-request";
import type { ChangeServiceRequestStatusRequest } from "@/types/service-requests/change-service-request-status-request";
import type { CreateServiceRequestRequest } from "@/types/service-requests/create-service-request-request";
import type { ReassignServiceRequestRequest } from "@/types/service-requests/reassign-service-request-request";
import type { ServiceRequestDetail } from "@/types/service-requests/service-request-detail";
import type { ServiceRequestSummary } from "@/types/service-requests/service-request-summary";
import type { ApiResourceResponse } from "@/types/shared/api-resource-response";
import type { PaginatedResponse } from "@/types/shared/paginated-response";
import type { UserReference } from "@/types/shared/user-reference";

export class ServiceRequestService extends BaseApiService {
  async createServiceRequest(request: CreateServiceRequestRequest): Promise<ServiceRequestDetail> {
    const response = await this.post<
      ApiResourceResponse<ServiceRequestDetail>,
      CreateServiceRequestRequest
    >(ApiEndpoint.ServiceRequests, request);

    return response.data;
  }

  async listServiceRequests(page: number): Promise<PaginatedResponse<ServiceRequestSummary>> {
    return this.get<PaginatedResponse<ServiceRequestSummary>>(ApiEndpoint.ServiceRequests, {
      params: { [PAGE_QUERY_PARAMETER]: page },
    });
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

  async listAssignees(): Promise<UserReference[]> {
    const response = await this.get<ApiResourceResponse<UserReference[]>>(
      ApiEndpoint.ServiceRequestAssignees,
    );

    return response.data;
  }

  async assignToSelf(
    serviceRequestId: number,
    request: AssignServiceRequestRequest,
  ): Promise<ServiceRequestDetail> {
    const response = await this.post<
      ApiResourceResponse<ServiceRequestDetail>,
      AssignServiceRequestRequest
    >(serviceRequestAssignmentEndpoint(serviceRequestId), request);

    return response.data;
  }

  async reassign(
    serviceRequestId: number,
    request: ReassignServiceRequestRequest,
  ): Promise<ServiceRequestDetail> {
    const response = await this.post<
      ApiResourceResponse<ServiceRequestDetail>,
      ReassignServiceRequestRequest
    >(serviceRequestReassignmentEndpoint(serviceRequestId), request);

    return response.data;
  }

  async changeStatus(
    serviceRequestId: number,
    request: ChangeServiceRequestStatusRequest,
  ): Promise<ServiceRequestDetail> {
    const response = await this.patch<
      ApiResourceResponse<ServiceRequestDetail>,
      ChangeServiceRequestStatusRequest
    >(serviceRequestStatusEndpoint(serviceRequestId), request);

    return response.data;
  }
}

export const serviceRequestService = new ServiceRequestService();
