import type { NextConfig } from "next";

/**
 * Static export untuk shared hosting cPanel (tanpa Node.js).
 * DILARANG: API routes, middleware/proxy, SSR, server actions, rewrites, ISR, next/image optimizer.
 */
const nextConfig: NextConfig = {
  output: "export",
  trailingSlash: true,
  images: { unoptimized: true },
  reactStrictMode: true,
  poweredByHeader: false,
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
