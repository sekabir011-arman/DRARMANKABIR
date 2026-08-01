#!/usr/bin/env python3
"""Fix the legacy auth blocks in PrescriptionPadPreview.tsx."""
import sys

path = "source_build/dr.armankabir-main/src/frontend/src/components/PrescriptionPadPreview.tsx"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# ─── Block 1: getDoctorProfile (legacy session lookup) ───────────────────────
old_profile = '''  const getDoctorProfile = () => {
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
          if (profile) return profile;
        }
      }
    } catch {
      /* ignore */
    }
    return fallbackDoctorInfo;
  };'''

new_profile = '''  const getDoctorProfile = () => {
    try {
      const email = getDoctorEmail();
      if (email) {
        const profile = JSON.parse(
          storage.getItem(`doctor_profile_${email}`) || "null",
        );
        if (profile) return profile;
      }
    } catch {
      /* ignore */
    }
    return fallbackDoctorInfo;
  };'''

# ─── Block 2: getDoctorDisplayName (legacy session lookup) ───────────────────
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

count1 = content.count(old_profile)
count2 = content.count(old_name)
print(f"getDoctorProfile block: {count1} occurrence(s)")
print(f"getDoctorDisplayName block: {count2} occurrence(s)")

ok = True
if count1 == 1:
    content = content.replace(old_profile, new_profile)
    print("getDoctorProfile replaced")
elif not count1:
    print("ERROR: getDoctorProfile block not found")
    ok = False
else:
    print("ERROR: getDoctorProfile multiple occurrences")
    ok = False

if count2 == 1:
    content = content.replace(old_name, new_name)
    print("getDoctorDisplayName replaced")
elif not count2:
    print("ERROR: getDoctorDisplayName block not found")
    ok = False
else:
    print("ERROR: getDoctorDisplayName multiple occurrences")
    ok = False

if ok:
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)
    print("Saved successfully")
else:
    sys.exit(1)
