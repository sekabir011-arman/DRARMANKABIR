#!/bin/bash
cd /home/drarmank
line=$(grep -n "pendingChanges" source_build/dr.armankabir-main/src/frontend/src/hooks/useMigration.ts | cut -d: -f1)
echo "Line: $line"
# Read the current file
mapfile -t lines < source_build/dr.armankabir-main/src/frontend/src/hooks/useMigration.ts
echo "Before: ${lines[$((line-1))]}"
# Replace the line
lines[$((line-1))]="    pendingChanges: ,"
printf '%s\n' "${lines[@]}" > source_build/dr.armankabir-main/src/frontend/src/hooks/useMigration.ts
echo "After: $(sed -n ${line}p source_build/dr.armankabir-main/src/frontend/src/hooks/useMigration.ts)"
