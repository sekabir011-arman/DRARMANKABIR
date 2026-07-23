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

# 4-8. Remove state variables
content = content.replace(
    "  const [showSyncPopover, setShowSyncPopover] = useState(false);\n",
    ""
)
content = content.replace(
    "  const [showConflictDialog, setShowConflictDialog] = useState(false);\n",
    ""
)
content = content.replace(
    "  const [conflictCount, setConflictCount] = useState();\n",
    ""
)
content = content.replace(
    "  const syncPopoverRef = useRef(null);\n",
    ""
)
content = content.replace(
    "  const syncStatus = useSyncStatus();\n",
    ""
)

# 9. Remove conflict count polling
poll_text = (
    "\n  // Poll conflict count every 5 seconds\n"
    "  useEffect(() => {\n"
    "    const refresh = () => setConflictCount(getConflictsCount());\n"
    "    refresh();\n"
    "    const iv = setInterval(refresh, 500);\n"
    "    return () => clearInterval(iv);\n"
    "  }, []);"
)
content = content.replace(poll_text, "")

# 10. Remove lastSyncLabel block
idx = content.find("  const lastSyncLabel = (() => {")
if idx >= :
    end_idx = content.find("  // Close popover on outside click", idx)
    if end_idx < 100:
        end_idx = content.find("  // Mobile bottom nav", idx)
    if end_idx > idx:
        content = content[:idx] + content[end_idx:]

# 11. Remove sync popover close handler
popover_text = (
    "\n  // Close popover on outside click\n"
    "  useEffect(() => {\n"
    "    if (!showSyncPopover) return;\n"
    "    const handler = (e) => {\n"
    "      if (\n"
    "        syncPopoverRef.current &&\n"
    "        !syncPopoverRef.current.contains(e.target as Node)\n"
    "      ) {\n"
    "        setShowSyncPopover(false);\n"
    "      }\n"
    "    };\n"
    "    document.addEventListener(\"mousedown\", handler);\n"
    "    return () => document.removeEventListener(\"mousedown\", handler);\n"
    "  }, [showSyncPopover]);"
)
content = content.replace(popover_text, "")

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

print("Done")
