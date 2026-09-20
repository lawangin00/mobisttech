import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  poweredByHeader: false,
  async redirects() {
    // Preserve original public catalogue URLs as same-site, permanent HTTP 301 redirects.
    return [
      { source: "/mobiles", destination: "/products?category=mobile_phone", statusCode: 301 },
      { source: "/mobiles/:slug", destination: "/products/:slug", statusCode: 301 },
      { source: "/product/:slug", destination: "/products/:slug", statusCode: 301 },
      { source: "/products/mobiles", destination: "/products?category=mobile_phone", statusCode: 301 },
      { source: "/products/tablets", destination: "/products?category=tablet", statusCode: 301 },
      { source: "/products/accessories", destination: "/products?category=accessory", statusCode: 301 },
    ];
  },
  async rewrites() {
    // Fixed target proxy only; Laravel owns all API responses and business operations.
    return [
      { source: "/api/v1/:path*", destination: "http://127.0.0.1:18080/api/v1/:path*" },
      { source: "/sanctum/csrf-cookie", destination: "http://127.0.0.1:18080/sanctum/csrf-cookie" },
    ];
  },
};
export default nextConfig;
