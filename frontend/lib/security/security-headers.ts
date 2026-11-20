// Static security headers applied to every frontend response through next.config.ts.
// Values match the backend, and HSTS is added only in production.

import { isProductionRuntime } from "../../config/runtime-environment";

export interface SecurityHeader {
  key: string;
  value: string;
}

export enum SecurityHeaderName {
  ContentTypeOptions = "X-Content-Type-Options",
  FrameOptions = "X-Frame-Options",
  ReferrerPolicy = "Referrer-Policy",
  PermissionsPolicy = "Permissions-Policy",
  StrictTransportSecurity = "Strict-Transport-Security",
}

const COMMON_SECURITY_HEADERS: SecurityHeader[] = [
  { key: SecurityHeaderName.ContentTypeOptions, value: "nosniff" },
  { key: SecurityHeaderName.FrameOptions, value: "DENY" },
  { key: SecurityHeaderName.ReferrerPolicy, value: "strict-origin-when-cross-origin" },
  {
    key: SecurityHeaderName.PermissionsPolicy,
    value: "camera=(), microphone=(), geolocation=(), payment=()",
  },
];

const STRICT_TRANSPORT_SECURITY_HEADER: SecurityHeader = {
  key: SecurityHeaderName.StrictTransportSecurity,
  value: "max-age=31536000; includeSubDomains",
};

export const SECURITY_HEADERS: readonly SecurityHeader[] = isProductionRuntime
  ? [...COMMON_SECURITY_HEADERS, STRICT_TRANSPORT_SECURITY_HEADER]
  : COMMON_SECURITY_HEADERS;
