// Wraps the authentication endpoints for sign in, sign out, the current user and password change.
// Unwraps the resource envelope so callers receive the current user directly.

import { ApiEndpoint } from "@/constants/api-endpoints";
import { BaseApiService } from "@/services/shared/base-api-service";
import type { ChangePasswordRequest } from "@/types/auth/change-password-request";
import type { CurrentUser } from "@/types/auth/current-user";
import type { SignInRequest } from "@/types/auth/sign-in-request";
import type { ApiResourceResponse } from "@/types/shared/api-resource-response";

export class AuthService extends BaseApiService {
  async signIn(request: SignInRequest): Promise<CurrentUser> {
    const response = await this.post<ApiResourceResponse<CurrentUser>, SignInRequest>(
      ApiEndpoint.SignIn,
      request,
    );

    return response.data;
  }

  async signOut(): Promise<void> {
    await this.post<void>(ApiEndpoint.SignOut);
  }

  async getCurrentUser(): Promise<CurrentUser> {
    const response = await this.get<ApiResourceResponse<CurrentUser>>(ApiEndpoint.CurrentUser);

    return response.data;
  }

  async changePassword(request: ChangePasswordRequest): Promise<CurrentUser> {
    const response = await this.patch<ApiResourceResponse<CurrentUser>, ChangePasswordRequest>(
      ApiEndpoint.ChangePassword,
      request,
    );

    return response.data;
  }
}

export const authService = new AuthService();
