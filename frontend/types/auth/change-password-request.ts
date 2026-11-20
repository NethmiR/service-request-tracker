// Body sent to the self password change endpoint.
// Field names mirror the backend ChangePasswordRequest.

export interface ChangePasswordRequest {
  current_password: string;
  password: string;
  password_confirmation: string;
}
