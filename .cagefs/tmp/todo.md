# Remaining Files to Fix (Legacy Auth Keys)

## File 1: PrescriptionPad.tsx
- Line 171: `storage.getItem("medicare_current_doctor")` 
- Line 174: `storageAdapter.getItem("medicare_doctors_registry")`
- Line 457: `storage.getItem("medicare_current_doctor")`
- Line 460: `storageAdapter.getItem("medicare_doctors_registry")`

## File 2: PatientDashboard.tsx
- Line 1471: `storage.getItem("staff_auth")`
- Lines 3612, 4431, 4491, 451, 4567, 4589, 4612: `storage.getItem("medicare_current_doctor")`
- Lines 3617, 4436, 445, 4458, 4496, 4518, 4575, 4597, 4617: `storageAdapter.getItem("medicare_doctors_registry")`

## File 3: UpgradedPrescriptionEMR.tsx
- Line 1693: `storage.getItem("medicare_current_doctor")`
- Line 1696: `storageAdapter.getItem("medicare_doctors_registry")`

## File 4: DischargeSummaryTab.tsx
- Line 74: `storage.getItem("staff_auth")`

## File 5: PrescriptionPadPreview.tsx
- Line 173: `storage.getItem("medicare_current_doctor")`
- Line 176: `storageAdapter.getItem("medicare_doctors_registry")`
- Line 355: `storage.getItem("medicare_current_doctor")`
- Line 358: `storageAdapter.getItem("medicare_doctors_registry")`

## File 6: LandingPage.tsx
- Lines 2662, 2675, 2693: `storageAdapter.getItem("medicare_doctors_registry")`
- Lines 2681, 2699: `storageAdapter.setItem("medicare_doctors_registry", ...)`
