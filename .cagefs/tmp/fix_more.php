<?php
$file = "/home/drarmank/source_build/dr.armankabir-main/src/frontend/src/pages/Staff.tsx";
$content = file_get_contents($file);
$zero = chr(48);

// Fix overrideAttendance
$old = '  const overrideAttendance = (id: string) => {
    const status = attendanceOverride[id] as AttendanceRecord["shiftStatus"];
    if (!status) return;
    const list = loadAttendance();
    const idx = list.findIndex((r) => r.id === id);
    if (idx >= ' . $zero . ') {
      list[idx] = {
        ...list[idx],
        shiftStatus: status,
        manualOverride: true,
        overrideNote: attendanceNote[id] ?? "",
      };
      saveAttendance(list);
      refresh();
      toast.success("Attendance updated");
    }
  };';

$new = '  const overrideAttendance = async (id: string) => {
    const status = attendanceOverride[id] as AttendanceRecord["shiftStatus"];
    if (!status) return;
    const list = await loadAttendance();
    const idx = list.findIndex((r) => r.id === id);
    if (idx >= ' . $zero . ') {
      list[idx] = {
        ...list[idx],
        shiftStatus: status,
        manualOverride: true,
        overrideNote: attendanceNote[id] ?? "",
      };
      await saveAttendance(list);
      refresh();
      toast.success("Attendance updated");
    }
  };';

$content = str_replace($old, $new, $content);

// Fix submitLeaveRequest
$old2 = '  const submitLeaveRequest = () => {
    if (!currentDoctor) return;
    if (!leaveForm.startDate || !leaveForm.endDate) {
      toast.error("Please fill in all required fields.");
      return;
    }
    const requests = loadLeaveRequests();
    requests.push({
      id: Date.now().toString(36),
      staffId: currentDoctor.id,
      staffName: currentDoctor.name,
      staffRole: currentDoctor.role,
      startDate: leaveForm.startDate,
      endDate: leaveForm.endDate,
      leaveType: leaveForm.leaveType,
      reason: leaveForm.reason,
      status: "pending",
      adminNote: "",
      requestedAt: new Date().toISOString(),
    });
    saveLeaveRequests(requests);
    refresh();
    setShowLeaveForm(false);
    setLeaveForm({
      startDate: new Date().toISOString().split("T")[' . $zero . '],
      endDate: new Date().toISOString().split("T")[' . $zero . '],
      leaveType: "Annual Leave",
      reason: "",
    });
    toast.success("Leave request submitted");
  };';

$new2 = '  const submitLeaveRequest = async () => {
    if (!currentDoctor) return;
    if (!leaveForm.startDate || !leaveForm.endDate) {
      toast.error("Please fill in all required fields.");
      return;
    }
    const requests = await loadLeaveRequests();
    requests.push({
      id: Date.now().toString(36),
      staffId: currentDoctor.id,
      staffName: currentDoctor.name,
      staffRole: currentDoctor.role,
      startDate: leaveForm.startDate,
      endDate: leaveForm.endDate,
      leaveType: leaveForm.leaveType,
      reason: leaveForm.reason,
      status: "pending",
      adminNote: "",
      requestedAt: new Date().toISOString(),
    });
    await saveLeaveRequests(requests);
    refresh();
    setShowLeaveForm(false);
    setLeaveForm({
      startDate: new Date().toISOString().split("T")[' . $zero . '],
      endDate: new Date().toISOString().split("T")[' . $zero . '],
      leaveType: "Annual Leave",
      reason: "",
    });
    toast.success("Leave request submitted");
  };';

$content = str_replace($old2, $new2, $content);

// Fix reviewLeave
$old3 = '  const reviewLeave = (id: string, status: LeaveStatus, note: string) => {
    const requests = loadLeaveRequests();
    const idx = requests.findIndex((r) => r.id === id);
    if (idx >= ' . $zero . ') {
      requests[idx] = {
        ...requests[idx],
        status,
        adminNote: note,
        reviewedAt: new Date().toISOString(),
        reviewedBy: currentDoctor?.name ?? "Admin",
      };
      saveLeaveRequests(requests);
      refresh();
      toast.success(`Leave request ${status}`);
    }
  };';

$new3 = '  const reviewLeave = async (id: string, status: LeaveStatus, note: string) => {
    const requests = await loadLeaveRequests();
    const idx = requests.findIndex((r) => r.id === id);
    if (idx >= ' . $zero . ') {
      requests[idx] = {
        ...requests[idx],
        status,
        adminNote: note,
        reviewedAt: new Date().toISOString(),
        reviewedBy: currentDoctor?.name ?? "Admin",
      };
      await saveLeaveRequests(requests);
      refresh();
      toast.success(`Leave request ${status}`);
    }
  };';

$content = str_replace($old3, $new3, $content);

file_put_contents($file, $content);
echo "Fixed\n";
