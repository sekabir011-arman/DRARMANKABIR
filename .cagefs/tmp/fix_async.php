<?php
$file = "/home/drarmank/source_build/dr.armankabir-main/src/frontend/src/pages/Staff.tsx";
$content = file_get_contents($file);
$zero = chr(48);

// Fix submitLeaveRequest - change to async and use await
$search = "  const submitLeaveRequest = () => {
    if (!currentDoctor) return;
    if (!leaveForm.startDate || !leaveForm.endDate) {
      toast.error(\"Please fill in all required fields.\");
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
      status: \"pending\",
      adminNote: \"\",
      requestedAt: new Date().toISOString(),
    });
    saveLeaveRequests(requests);
    refresh();
    setShowLeaveForm(false);
    setLeaveForm({
      startDate: new Date().toISOString().split(\"T\")[],
      endDate: new Date().toISOString().split(\"T\")[],
      leaveType: \"Annual Leave\",
      reason: \"\",
    });
    toast.success(\"Leave request submitted\");
  };";

$replace = "  const submitLeaveRequest = async () => {
    if (!currentDoctor) return;
    if (!leaveForm.startDate || !leaveForm.endDate) {
      toast.error(\"Please fill in all required fields.\");
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
      status: \"pending\",
      adminNote: \"\",
      requestedAt: new Date().toISOString(),
    });
    await saveLeaveRequests(requests);
    refresh();
    setShowLeaveForm(false);
    setLeaveForm({
      startDate: new Date().toISOString().split(\"T\")[],
      endDate: new Date().toISOString().split(\"T\")[],
      leaveType: \"Annual Leave\",
      reason: \"\",
    });
    toast.success(\"Leave request submitted\");
  };";

$content = str_replace($search, $replace, $content);

// Fix reviewLeave  
$search2 = "  const reviewLeave = (id: string, status: LeaveStatus, note: string) => {
    const requests = loadLeaveRequests();
    const idx = requests.findIndex((r) => r.id === id);
    if (idx >= ) {
      requests[idx] = {
        ...requests[idx],
        status,
        adminNote: note,
        reviewedAt: new Date().toISOString(),
        reviewedBy: currentDoctor?.name ?? \"Admin\",
      };
      saveLeaveRequests(requests);
      refresh();
      toast.success(\`Leave request \${status}\`);
    }
  };";

$replace2 = "  const reviewLeave = async (id: string, status: LeaveStatus, note: string) => {
    const requests = await loadLeaveRequests();
    const idx = requests.findIndex((r) => r.id === id);
    if (idx >= " . $zero . ") {
      requests[idx] = {
        ...requests[idx],
        status,
        adminNote: note,
        reviewedAt: new Date().toISOString(),
        reviewedBy: currentDoctor?.name ?? \"Admin\",
      };
      await saveLeaveRequests(requests);
      refresh();
      toast.success(\`Leave request \${status}\`);
    }
  };";

$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "Fixed\n";
