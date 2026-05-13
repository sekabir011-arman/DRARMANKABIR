import { fileURLToPath, URL } from "url";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";
import environment from "vite-plugin-environment";

const isLocal = process.env.DFX_NETWORK === "local";

const ii_url = isLocal
  ? "http://rdmx6-jaaaa-aaaaa-aaadq-cai.localhost:4943/"
  : "https://identity.internetcomputer.org/";

process.env.II_URL = process.env.II_URL || ii_url;

process.env.STORAGE_GATEWAY_URL =
  process.env.STORAGE_GATEWAY_URL || "https://blob.caffeine.ai";

export default defineConfig({
  logLevel: "info",

  build: {
    emptyOutDir: true,
    sourcemap: false,
    minify: "esbuild",
  },

  css: {
    postcss: "./postcss.config.js",
  },

  optimizeDeps: {
    esbuildOptions: {
      define: {
        global: "globalThis",
      },
    },
  },

  server: isLocal
    ? {
        proxy: {
          "/api": {
            target: "http://127.0.0.1:4943",
            changeOrigin: true,
          },
        },
      }
    : undefined,

  plugins: [
    environment("all", { prefix: "CANISTER_" }),
    environment("all", { prefix: "DFX_" }),
    environment(["II_URL"]),
    environment(["STORAGE_GATEWAY_URL"]),
    react(),
  ],

  resolve: {
    alias: [
      {
        find: "declarations",
        replacement: fileURLToPath(
          new URL("../declarations", import.meta.url)
        ),
      },
      {
        find: "@",
        replacement: fileURLToPath(new URL("./src", import.meta.url)),
      },
    ],
    dedupe: ["@dfinity/agent"],
  },
});
