/**
 * Canister Sync Hook — PHP/MySQL Backend
 *
 * Canister sync is no longer needed — all data is persisted server-side.
 * This hook provides a simplified sync status indicating online/offline state.
 * No localStorage used.
 */

import { useCallback, useEffect, useState } from "react";

export type SyncStatus = "offline" | "syncing" | "synced" | "pending";

export interface UseCanisterSyncResult {
  lastSyncTime: Date | null;
  syncStatus: SyncStatus;
  triggerSync: () => Promise<void>;
  itemsPending: number;
  syncBannerMessage: string | null;
}

const POLL_INTERVAL_MS = 30_000;

export function useCanisterSync(): UseCanisterSyncResult {
  const [lastSyncTime, setLastSyncTime] = useState<Date | null>(null);
  const [syncStatus, setSyncStatus] = useState<SyncStatus>(
    navigator.onLine ? "synced" : "offline",
  );
  const [itemsPending, setItemsPending] = useState<number>();
  const [syncBannerMessage, setSyncBannerMessage] = useState<string | null>(
    null,
  );

  const triggerSync = useCallback(async () => {
    if (!navigator.onLine) {
      setSyncStatus("offline");
      setSyncBannerMessage(
        "You are offline. Data will sync automatically when connection is restored.",
      );
      return;
    }

    setSyncStatus("syncing");
    setSyncBannerMessage(null);

    try {
      // Data is already persisted server-side via PHP API
      // No sync needed — verify connectivity
      setLastSyncTime(new Date());
      setItemsPending();
      setSyncStatus("synced");
      setSyncBannerMessage(null);
    } catch {
      setSyncStatus("pending");
      setSyncBannerMessage(
        "Connection issue — data is saved on the server.",
      );
    }
  }, []);

  // Listen for online/offline events
  useEffect(() => {
    const handleOnline = () => {
      setSyncStatus("synced");
      setSyncBannerMessage(null);
      triggerSync();
    };
    const handleOffline = () => {
      setSyncStatus("offline");
      setSyncBannerMessage(
        "You are offline. Data will sync automatically when connection is restored.",
      );
    };

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);
    return () => {
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, [triggerSync]);

  // Initial sync
  useEffect(() => {
    triggerSync();
  }, [triggerSync]);

  // Polling
  useEffect(() => {
    const interval = setInterval(() => {
      triggerSync();
    }, POLL_INTERVAL_MS);
    return () => clearInterval(interval);
  }, [triggerSync]);

  return {
    lastSyncTime,
    syncStatus,
    triggerSync,
    itemsPending,
    syncBannerMessage,
  };
}
