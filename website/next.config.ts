import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  poweredByHeader: false,
  async rewrites() {
    // Fixed target proxy only; Laravel owns all API responses and business operations.
    return [
      { source: "/api/v1/:path*", destination: "http://127.0.0.1:18080/api/v1/:path*" },
      { source: "/sanctum/csrf-cookie", destination: "http://127.0.0.1:18080/sanctum/csrf-cookie" },
    ];
  },
};
export default nextConfig;
