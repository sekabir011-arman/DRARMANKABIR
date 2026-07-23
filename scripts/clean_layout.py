#!/usr/bin/env python3
"""Clean up Layout.tsx - remove all ICP/sync related code."""

import re

with open("source_build/dr.armankabir-main/src/frontend/src/Layout.tsx", "r") as f:
    content = f.read()

# 1. Remove SyncConflictDialog import
content = content.replace(
    'import SyncConflictDialog from "./components/SyncConflictDialog";\n',
    ""
)

# 2. Remove useSyncStatus import
content = content.replace(
    'import { useSyncStatus } from "./hooks/useMigration";\n',
    ""
)

# 3. Remove getConflictsCount import
content = content.replace(
    'import { getConflictsCount } from "./lib/hybridStorage";\n',
    ""
)

# 4. Remove showSyncPopover state line
content = content.replace(
    "  const [showSyncPopover, setShowSyncPopover] = useState(false);\n",
    ""
)

# 5. Remove showConflictDialog state line
content = content.replace(
    "  const [showConflictDialog, setShowConflictDialog] = useState(false);\n",
    ""
)

# 6. Remove conflictCount state line
content = content.replace(
    "  const [conflictCount, setConflictCount] = useState();\n",
    ""
)

# 7. Remove syncPopoverRef
content = content.replace(
    "  const syncPopoverRef = useRef<HTMLDivElement>(null);\n",
    ""
)

# 8. Remove syncStatus
content = content.replace(
    "  const syncStatus = useSyncStatus();\n",
    ""
)

# 9. Remove conflict count polling
content = content.replace(
    """  // Poll conflict count every 5 seconds
  useEffect(() => {
    const refresh = () => setConflictCount(getConflictsCount());
    refresh();
    const iv = setInterval(refresh, 500);
    return () => clearInterval(iv);
  }, []);""",
    ""
)

# 10. Remove lastSyncLabel block
content = content.replace(
    """  const lastSyncLabel = (() => {
    if (!syncStatus.lastSyncAt) return "Never synced";
    const diffMs = Date.now() - syncStatus.lastSyncAt.getTime();
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return "Just now";
    if (diffMin === 1) return "1 min ago";
    if (diffMin < 60) return `${diffMin} min ago`;
    return `${Math.floor(diffMin / 60)}h ago`;
  })();

  const lastSyncTime = (() => {
    if (!syncStatus.lastSyncAt) return "";
    return syncStatus.lastSyncAt.toLocaleTimeString("en-BD", {
      hour: "2-digit",
      minute: "2-digit",
    });
  })();

  const syncIndicator = (() => {
    if (!isOnline)
      return {
        color: "bg-amber-500",
        label: `Offline (${syncStatus.pendingChanges} pending)`,
        tooltip: `Offline — ${syncStatus.pendingChanges} item(s) pending sync.`,
        icon: <WifiOff className="w-3 h-3" />,
        badgeClass: "bg-amber-100 text-amber-700 border-amber-200",
      };
    if (syncStatus.pendingChanges > )
      return {
        color: "bg-yellow-400 animate-pulse",
        label: `Syncing... (${syncStatus.pendingChanges} pending)`,
        tooltip: `${syncStatus.pendingChanges} item(s) pending sync — last synced at ${lastSyncTime || "unknown"}`,
        icon: <RefreshCw className="w-3 h-3 animate-spin" />,
        badgeClass: "bg-yellow-100 text-yellow-700 border-yellow-200",
      };
    return {
      color: "bg-green-500",
      label: "All synced",
      tooltip: `All data synced — last synced at ${lastSyncTime || lastSyncLabel}`,
      icon: <Wifi className="w-3 h-3" />,
      badgeClass: "bg-green-100 text-green-700 border-green-200",
    };
  })();""",
    ""
)

# 11. Remove sync popover close handler
content = content.replace(
    """  // Close popover on outside click
  useEffect(() => {
    if (!showSyncPopover) return;
    const handler = (e: MouseEvent) => {
      if (
        syncPopoverRef.current &&
        !syncPopoverRef.current.contains(e.target as Node)
      ) {
        setShowSyncPopover(false);
      }
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, [showSyncPopover]);""",
    ""
)

# 12. Remove sync conflict badge + sync popover JSX
# Find the block starting from "Sync conflict badge" comment
start_pattern = """              {/* Sync conflict badge */}
              {conflictCount >  && ("""
end_marker = """                </div>
              </div>
            </div>"""

start_idx = content.find(start_pattern)
if start_idx >= :
    # Find the end by looking for the popover section's closing divs
    # After the sync popover, the next JSX will be the mobile menu button
    next_section = content.find("              {/* Mobile menu button */}", start_idx)
    if next_section > start_idx:
        # Remove everything from start_pattern to just before next_section
        before = content[:start_idx]
        after = content[next_section:]
        content = before + after

# 13. Remove SyncConflictDialog usage at the bottom
content = content.replace(
    """      <SyncConflictDialog
        open={showConflictDialog}
        onOpenChange={setShowConflictDialog}
      />""",
    ""
)

with open("source_build/dr.armankabir-main/src/frontend/src/Layout.tsx", "w") as f:
    f.write(content)

print("Done")
