// Wraps the admin user management endpoints for listing, viewing, creating and resetting users and managing their sessions.
// Unwraps the resource envelope everywhere except the paginated list, which keeps its links and meta.

import {
  ApiEndpoint,
  userEndpoint,
  userPasswordResetEndpoint,
  userSessionEndpoint,
  userSessionsEndpoint,
} from "@/constants/api-endpoints";
import { PAGE_QUERY_PARAMETER } from "@/constants/http";
import { BaseApiService } from "@/services/shared/base-api-service";
import type { UserSession } from "@/types/sessions/user-session";
import type { ApiResourceResponse } from "@/types/shared/api-resource-response";
import type { PaginatedResponse } from "@/types/shared/paginated-response";
import type { CreateUserRequest } from "@/types/users/create-user-request";
import type { IssuedCredentials } from "@/types/users/issued-credentials";
import type { ManagedUser } from "@/types/users/managed-user";

export class UserService extends BaseApiService {
  async listUsers(page: number): Promise<PaginatedResponse<ManagedUser>> {
    return this.get<PaginatedResponse<ManagedUser>>(ApiEndpoint.Users, {
      params: { [PAGE_QUERY_PARAMETER]: page },
    });
  }

  async getUser(userId: number): Promise<ManagedUser> {
    const response = await this.get<ApiResourceResponse<ManagedUser>>(userEndpoint(userId));

    return response.data;
  }

  async createUser(request: CreateUserRequest): Promise<IssuedCredentials> {
    const response = await this.post<ApiResourceResponse<IssuedCredentials>, CreateUserRequest>(
      ApiEndpoint.Users,
      request,
    );

    return response.data;
  }

  async resetPassword(userId: number): Promise<IssuedCredentials> {
    const response = await this.post<ApiResourceResponse<IssuedCredentials>>(
      userPasswordResetEndpoint(userId),
    );

    return response.data;
  }

  async listUserSessions(userId: number): Promise<UserSession[]> {
    const response = await this.get<ApiResourceResponse<UserSession[]>>(
      userSessionsEndpoint(userId),
    );

    return response.data;
  }

  async clearUserSession(userId: number, sessionIdentifier: string): Promise<void> {
    await this.delete<void>(userSessionEndpoint(userId, sessionIdentifier));
  }

  async clearUserSessions(userId: number): Promise<void> {
    await this.delete<void>(userSessionsEndpoint(userId));
  }
}

export const userService = new UserService();
