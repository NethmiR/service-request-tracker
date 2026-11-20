// Next.js configuration for the frontend.
// Hides the framework header, applies the static security headers and wires Tailwind into Turbopack.

import type { NextConfig } from "next";
import { SECURITY_HEADERS } from "./lib/security/security-headers";

const nextConfig: NextConfig = {
  poweredByHeader: false,
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [...SECURITY_HEADERS],
      },
    ];
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
