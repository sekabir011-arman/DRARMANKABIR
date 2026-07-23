#!/usr/bin/env python3
"""Clean up Layout.tsx - remove all ICP/sync related code."""

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

# 4-8. Remove state variables that are no longer needed
import re

# Remove showSyncPopover state
content = content.replace(
    "  const [showSyncPopover, setShowSyncPopover] = useState(false);\n",
    ""
)

# Remove showConflictDialog state
content = content.replace(
    "  const [showConflictDialog, setShowConflictDialog] = useState(false);\n",
    ""
)

# Remove conflictCount state
content = content.replace(
    "  const [conflictCount, setConflictCount] = useState();\n",
    ""
)

# Remove syncPopoverRef
content = content.replace(
    "  const syncPopoverRef = useRef<HTMLDivElement>(null);\n",
    ""
)

# Remove syncStatus
content = content.replace(
    "  const syncStatus = useSyncStatus();\n",
    ""
)

# 9. Remove conflict count polling
poll_text = """  // Poll conflict count every 5 seconds
  useEffect(() => {
    const refresh = () => setConflictCount(getConflictsCount());
    refresh();
    const iv = setInterval(refresh, 500);
    return () => clearInterval(iv);
  }, []);"""
content = content.replace(poll_text, "")

# 10. Remove lastSyncLabel / lastSyncTime / syncIndicator block
idx = content.find("  const lastSyncLabel = (() => {")
if idx >= :
    end_idx = content.find("\n  // Close popover on outside click", idx)
    if end_idx < :
        end_idx = content.find("\n  // Mobile bottom nav", idx)
    if end_idx > idx:
        content = content[:idx] + content[end_idx:]

# 11. Remove sync popover close handler
popover_effect = """  // Close popover on outside click
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
  }, [showSyncPopover]);"""
content = content.replace(popover_effect, "")

# 12. Remove sync conflict badge + sync popover JSX
start_marker = "              {/* Sync conflict badge */}"
end_marker = "              {/* Mobile menu button */}"
idx_start = content.find(start_marker)
idx_end = content.find(end_marker)
if idx_start >=  and idx_end > idx_start:
    content = content[:idx_start] + content[idx_end:]

# 13. Remove SyncConflictDialog usage at the bottom
content = content.replace(
    "      <SyncConflictDialog\n"
    "        open={showConflictDialog}\n"
    "        onOpenChange={setShowConflictDialog}\n"
    "      />",
    ""
)

with open("source_build/dr.armankabir-main/src/frontend/src/Layout.tsx", "w") as f:
    f.write(content)

print("Done cleaning Layout.tsx")
