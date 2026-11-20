// Body sent to the sign in endpoint.
// Field names mirror the backend SignInRequest.

export interface SignInRequest {
  email: string;
  password: string;
}
