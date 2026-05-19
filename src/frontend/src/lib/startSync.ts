import { runSyncCycle } from "./SyncEngine";

export function startSyncEngine() {
  // initial run
  runSyncCycle();

  // interval sync (production-safe)
  setInterval(() => {
    runSyncCycle();
  }, 5000);
}
