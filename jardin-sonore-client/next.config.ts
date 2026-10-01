import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  logging: {incomingRequests: {ignore: [/\/newsletter\/confirmer\/confirmation/, /\/api\/newsletter\/confirmations\/confirmation/, /\/newsletter\/confirmer\/[a-f0-9]{64}/, /\/api\/newsletter\/confirmations\/[a-f0-9]{64}/]}},
  async headers() {
    return [{source: "/newsletter/confirmer/confirmation", headers: [{key: "Referrer-Policy", value: "no-referrer"}]}];
  },
  experimental: {
    serverActions: {
      // The profile accepts a 2 MB photo; multipart metadata needs a small margin.
      bodySizeLimit: "3mb",
    },
  },
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "placehold.co",
        port: "",
        pathname: "/**",
      },
    ],
  },
};

export default nextConfig;
