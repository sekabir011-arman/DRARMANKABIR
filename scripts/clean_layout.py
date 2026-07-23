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

# 4. Remove showSyncPopover state
content = content.replace(
    "  const [showSyncPopover, setShowSyncPopover] = useState(false);\n",
    ""
)

# 5. Remove showConflictDialog state
content = content.replace(
    "  const [showConflictDialog, setShowConflictDialog] = useState(false);\n",
    ""
)

# 6. Remove conflictCount state
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
    "\n  // Poll conflict count every 5 seconds\n"
    "  useEffect(() => {\n"
    "    const refresh = () => setConflictCount(getConflictsCount());\n"
    "    refresh();\n"
    "    const iv = setInterval(refresh, 500);\n"
    "    return () => clearInterval(iv);\n"
    "  }, []);",
    ""
)

# 10. Remove lastSyncLabel / lastSyncTime / syncIndicator block
# Find it by looking for the unique comment
idx = content.find("  const lastSyncLabel = (() => {")
if idx >= :
    end_idx = content.find("\n  // Close popover on outside click", idx)
    if end_idx < :
        end_idx = content.find("\n  // Mobile bottom nav", idx)
    if end_idx > idx:
        content = content[:idx] + content[end_idx:]

# 11. Remove sync popover close handler
content = content.replace(
    "\n  // Close popover on outside click\n"
    "  useEffect(() => {\n"
    "    if (!showSyncPopover) return;\n"
    "    const handler = (e: MouseEvent) => {\n"
    "      if (\n"
    "        syncPopoverRef.current &&\n"
    "        !syncPopoverRef.current.contains(e.target as Node)\n"
    "      ) {\n"
    "        setShowSyncPopover(false);\n"
    "      }\n"
    "    };\n"
    "    document.addEventListener(\"mousedown\", handler);\n"
    "    return () => document.removeEventListener(\"mousedown\", handler);\n"
    "  }, [showSyncPopover]);",
    ""
)

# 12. Remove sync conflict badge + sync popover JSX
# Find the entire block from "Sync conflict badge" comment to before "Mobile menu button"
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
