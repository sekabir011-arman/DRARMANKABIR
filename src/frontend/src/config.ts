import env from "./env.json";

export const config = {
  backendHost: env.backend_host,

  canisterId:
    import.meta.env.VITE_CANISTER_ID_BACKEND,

  network:
    import.meta.env.VITE_DFX_NETWORK,

  iiUrl: env.ii_url,

  derivationOrigin:
    import.meta.env.VITE_II_DERIVATION_ORIGIN,
};
