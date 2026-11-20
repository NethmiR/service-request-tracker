// Adds the typed retry flag used by the CSRF retry logic to the Axios request config.
// Keeps the interceptor free of untyped properties.

import "axios";

declare module "axios" {
  interface InternalAxiosRequestConfig {
    hasRetriedCsrfToken?: boolean;
  }
}
