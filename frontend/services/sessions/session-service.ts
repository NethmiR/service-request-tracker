// Wraps the endpoints that list the signed in user's sessions and end them.
// Unwraps the resource envelope so callers receive the session list directly.

import { ApiEndpoint, sessionEndpoint } from "@/constants/api-endpoints";
import { BaseApiService } from "@/services/shared/base-api-service";
import type { UserSession } from "@/types/sessions/user-session";
import type { ApiResourceResponse } from "@/types/shared/api-resource-response";

export class SessionService extends BaseApiService {
  async listOwnSessions(): Promise<UserSession[]> {
    const response = await this.get<ApiResourceResponse<UserSession[]>>(ApiEndpoint.Sessions);

    return response.data;
  }

  async clearSession(sessionIdentifier: string): Promise<void> {
    await this.delete<void>(sessionEndpoint(sessionIdentifier));
  }

  async clearOtherSessions(): Promise<void> {
    await this.delete<void>(ApiEndpoint.OtherSessions);
  }
}

export const sessionService = new SessionService();
