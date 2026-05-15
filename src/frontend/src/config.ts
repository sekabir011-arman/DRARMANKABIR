export const config = {
  // ICP backend host (Vercel + local fallback)
  backendHost:
    import.meta.env.VITE_BACKEND_HOST ||
    "https://icp-api.io",

  // Backend canister ID (MOST IMPORTANT VALUE)
  canisterId:
    import.meta.env.VITE_CANISTER_ID_BACKEND || "",

  // Network type: local | ic
  network:
    import.meta.env.VITE_DFX_NETWORK || "ic",

  // Internet Identity URL
  iiUrl:
    import.meta.env.VITE_II_URL ||
    "https://identity.internetcomputer.org",

  // Identity derivation origin (important for auth consistency)
  derivationOrigin:
    import.meta.env.VITE_II_DERIVATION_ORIGIN || "",

  // Frontend origin (used for auth redirects / cookies)
  frontendOrigin:
    import.meta.env.VITE_FRONTEND_ORIGIN || "",
};

// Validation: Warn if critical config is missing
if (!config.canisterId) {
  console.warn(
    "⚠️ WARNING: VITE_CANISTER_ID_BACKEND environment variable is not set. " +
    "Backend sync will fail. Please set it in your .env file."
  );
}

if (!config.frontendOrigin) {
  console.warn(
    "⚠️ WARNING: VITE_FRONTEND_ORIGIN environment variable is not set. " +
    "Authentication redirects may not work correctly."
  );
}

export default config;
