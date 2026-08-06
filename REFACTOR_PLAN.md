# REFACTOR PLAN — Phase 2

Goal
- Refactor the canonical frontend source (source_build/dr.armankabir-main/src/frontend/) so it can be deployed as a production-ready cPanel PHP8 + MySQL application. Phase 2 scope is frontend-only refactor and repo-wide reporting. No production builds are run in this change; all edits are limited to the specified frontend source tree.

Scope (done now)
- Modify only: source_build/dr.armankabir-main/src/frontend/**
- Do NOT edit built assets or the deployment output (public_html/). Do NOT extract or duplicate the dr.armankabir-main.zip.

High-level approach
1. Inventory: produce canonical lists and reports (this repository). These are in the companion markdown files.
2. Sanitize browser storage: implement a single storage abstraction that enforces the policy:
   - Only allowed keys in localStorage: theme, language, sidebarState, uiPreferences (or namespaced equivalents).
   - Any attempt to persist business data to browser storage will be blocked and logged.
3. Remove legacy and blockchain-related code from the frontend source: locate artifacts for @dfinity, canister, ICP, IndexedDB-based StorageAdapter and offline-sync. Mark them for deletion and avoid shipping them to production.
4. Remove client-side business-data: ensure all CRUD in the frontend call backend PHP REST endpoints (this step is detection + refactor requests; the frontend wrapper will prevent accidental client-side persists). Full conversion of all CRUD usage will be staged and tested after review.
5. Tests & Lint: do not run builds in this change. Add TODOs and list files to change next; run tests in follow-up.

Immediate changes performed in this commit
- Added a defensive storage wrapper (source_build/dr.armankabir-main/src/frontend/src/utils/storage.ts) that centralises browser storage and enforces the allowed-key whitelist.
- Created repository-level reports (DATABASE_SCHEMA.md, API_ENDPOINT_MATRIX.md, REMOVED_LEGACY_CODE.md, and this REFACTOR_PLAN.md) with the full plan, inventories and file classification recommendations.

Next steps (after your review)
- Apply the DELETE/MODIFY actions listed in REMOVED_LEGACY_CODE.md (I will not delete files until you confirm):
  - Remove canister-related files from frontend (canister.yaml, caffeine.toml, biome.json) and clean package.json dependencies (@dfinity etc).
  - Replace any IndexedDB/StorageAdapter usage with API-backed flows.
  - Strip offline-sync code and the sync UI; convert the sync endpoints to server-side only.
- Replace ad-hoc localStorage uses across the codebase with the storage wrapper and migrate persisted values to the allowed keys only.
- Convert any client-side mock DB or business-data persistence to calls to public_html/api/ REST endpoints.
- After review, run the full test & build cycle in CI and iterate on bugfixes.

Rollback / safety
- No deletions were performed in this change. All modifications are additive and reversible.

Contact / Review
- Review the reports (DATABASE_SCHEMA.md, API_ENDPOINT_MATRIX.md, REMOVED_LEGACY_CODE.md) and approve the list of files to MODIFY/DELETE for the next change. After your approval I'll implement the file-level edits and run the build in a staging branch.
