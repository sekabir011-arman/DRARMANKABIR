cd /home/drarmank/source_build/dr.armankabir-main/src/frontend/src

# Change 1: Add getDoctorEmail import alongside useEmailAuth
sed -i 's|import { useEmailAuth } from "../hooks/useEmailAuth";|import { useEmailAuth } from "../hooks/useEmailAuth";\nimport { getDoctorEmail } from "../hooks/useQueries";|' pages/SerialDisplay.tsx

echo "Change 1 done: Added getDoctorEmail import"

# Change 2: Fix resolveVideoUrl to use getDoctorEmail() instead of storage.getItem("app_current_user_email")
sed -i 's|const email = storage.getItem("app_current_user_email");|const email = getDoctorEmail();|' pages/SerialDisplay.tsx

echo "Change 2 done: Fixed resolveVideoUrl email lookup"

# Change 3: Fix the canAddWalkIn call in SerialDisplayInner to use useEmailAuth context
# First, add the useEmailAuth destructuring after the existing state declarations
awk '
/const \[videoLoadError, setVideoLoadError\] = useState\(false\);/ {
    print
    print "  const { currentDoctor } = useEmailAuth();"
    print "  const allowWalkIn = canAddWalkInByRole(currentDoctor?.role);"
    next
}
{ print }
' pages/SerialDisplay.tsx > /tmp/patched.tsx && cp /tmp/patched.tsx pages/SerialDisplay.tsx

echo "Change 3 done: Added useEmailAuth context usage"

# Change 4: Remove the old allowWalkIn line that used canAddWalkIn()
sed -i '/^  const allowWalkIn = canAddWalkIn();$/d' pages/SerialDisplay.tsx

echo "Change 4 done: Removed old canAddWalkIn() call"

