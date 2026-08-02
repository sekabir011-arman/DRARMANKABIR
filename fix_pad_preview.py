#!/usr/bin/env python3
"""Fix getDoctorDisplayName legacy auth block in PrescriptionPadPreview.tsx."""
import sys

path = "source_build/dr.armankabir-main/src/frontend/src/components/PrescriptionPadPreview.tsx"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

old_name = '''  function getDoctorDisplayName(): string {
    try {
      const sessionId = storage.getItem("medicare_current_doctor");
      if (sessionId) {
        const registry = JSON.parse(
          storageAdapter.getItem("medicare_doctors_registry") || "[]",
        ) as Array<{ id: string; email: string }>;
        const doc = registry.find((d) => d.id === sessionId);
        if (doc?.email) {
          const profile = JSON.parse(
            storage.getItem(`doctor_profile_${doc.email}`) || "null",
          );
          if (profile?.name) return profile.name;
        }
      }
    } catch {
      /* ignore */
    }
    return "Dr. Arman Kabir (ZOSID)";
  }'''

new_name = '''  function getDoctorDisplayName(): string {
    try {
      const email = getDoctorEmail();
      if (email) {
        const profile = JSON.parse(
          storage.getItem(`doctor_profile_${email}`) || "null",
        );
        if (profile?.name) return profile.name;
      }
    } catch {
      /* ignore */
    }
    return "Dr. Arman Kabir (ZOSID)";
  }'''

count = content.count(old_name)
print(f"getDoctorDisplayName block: {count} occurrence(s)")

if count == 1:
    content = content.replace(old_name, new_name)
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)
    print("Saved successfully")
elif not count:
    print("ERROR: block not found")
    sys.exit(1)
else:
    print("ERROR: multiple occurrences - aborting")
    sys.exit(1)
