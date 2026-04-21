import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { useNavigate } from "@tanstack/react-router";
import {
  Activity,
  ArrowRight,
  BedDouble,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  ClipboardList,
  Clock,
  Eye,
  FileText,
  Loader2,
  PlusCircle,
  Users,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useEmailAuth } from "../../hooks/useEmailAuth";
import type { Patient } from "../../types";

interface LocalPatient extends Patient {
  bedNumber?: string;
  ward?: string;
  isAdmitted?: boolean;
}

interface DraftApprovalItem {
  id: string;
  patientName: string;
  patientId: string;
  internName: string;
  diagnosis: string;
  createdAt: string;
}

function loadAllPatients(): LocalPatient[] {
  const result: LocalPatient[] = [];
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    if (!k?.startsWith("patients_")) continue;
    try {
      const arr = JSON.parse(localStorage.getItem(k) || "[]") as LocalPatient[];
      result.push(...arr);
    } catch {}
  }
  return result;
}

function isAdmitted(p: LocalPatient) {
  return (
    p.isAdmitted === true ||
    p.patientType === "admitted" ||
    p.patientType === "indoor" ||
    String((p as Record<string, unknown>).status ?? "")
      .toLowerCase()
      .includes("admit")
  );
}

function loadPendingDrafts(): DraftApprovalItem[] {
  const results: DraftApprovalItem[] = [];
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    if (!k?.startsWith("prescriptions_")) continue;
    try {
      const arr = JSON.parse(localStorage.getItem(k) || "[]") as Array<
        Record<string, unknown>
      >;
      for (const rx of arr) {
        if (
          rx.status === "draft_awaiting_approval" ||
          (rx.isDraft === true && rx.internRole === true)
        ) {
          results.push({
            id: String(rx.id ?? ""),
            patientName: String(rx.patientName ?? "Unknown"),
            patientId: String(rx.patientId ?? ""),
            internName: String(rx.createdByName ?? rx.authorName ?? "Intern"),
            diagnosis: String(rx.diagnosis ?? "—"),
            createdAt: String(rx.createdAt ?? ""),
          });
        }
      }
    } catch {}
  }
  return results.sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

function getRecentActivity() {
  const logs: Array<{
    timestamp: string;
    userName: string;
    action: string;
    target: string;
  }> = [];
  try {
    const raw = localStorage.getItem("medicare_audit_log");
    if (raw) {
      const all = JSON.parse(raw) as typeof logs;
      return all.slice(-8).reverse();
    }
  } catch {}
  return logs;
}

export default function MedicalOfficerDashboard() {
  const { currentDoctor } = useEmailAuth();
  const navigate = useNavigate();
  const [patientFilter, setPatientFilter] = useState<"all" | "admitted">("all");
  const [draftsExpanded, setDraftsExpanded] = useState(false);

  const allPatients = useMemo(loadAllPatients, []);
  const recentActivity = useMemo(getRecentActivity, []);

  const [pendingDrafts, setPendingDrafts] = useState<DraftApprovalItem[]>(() =>
    loadPendingDrafts(),
  );

  // Refresh drafts every 30 seconds
  useEffect(() => {
    const interval = setInterval(() => {
      setPendingDrafts(loadPendingDrafts());
    }, 30_000);
    return () => clearInterval(interval);
  }, []);

  const admittedPatients = allPatients.filter(isAdmitted);
  const opdPatients = allPatients.filter((p) => !isAdmitted(p));
  const displayedPatients =
    patientFilter === "admitted" ? admittedPatients : allPatients;

  return (
    <div
      className="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-6"
      data-ocid="mo.dashboard"
    >
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">
            Welcome, {currentDoctor?.designation} {currentDoctor?.name}
          </h1>
          <p className="text-muted-foreground text-sm mt-0.5">
            Medical Officer Dashboard
          </p>
        </div>
        <Badge className="bg-green-100 text-green-800 border-green-200 text-xs px-3 py-1">
          Medical Officer
        </Badge>
      </div>

      {/* Quick stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <Card className="border-0 shadow-sm">
          <CardContent className="pt-5 pb-4 px-5 flex items-center gap-4">
            <div className="w-11 h-11 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center">
              <Users className="w-5 h-5" />
            </div>
            <div>
              <p className="text-2xl font-bold text-foreground leading-none">
                {allPatients.length}
              </p>
              <p className="text-xs text-muted-foreground mt-0.5">
                All Patients
              </p>
            </div>
          </CardContent>
        </Card>
        <Card className="border-0 shadow-sm">
          <CardContent className="pt-5 pb-4 px-5 flex items-center gap-4">
            <div className="w-11 h-11 rounded-xl bg-green-100 text-green-700 flex items-center justify-center">
              <BedDouble className="w-5 h-5" />
            </div>
            <div>
              <p className="text-2xl font-bold text-foreground leading-none">
                {admittedPatients.length}
              </p>
              <p className="text-xs text-muted-foreground mt-0.5">Admitted</p>
            </div>
          </CardContent>
        </Card>
        <Card className="border-0 shadow-sm">
          <CardContent className="pt-5 pb-4 px-5 flex items-center gap-4">
            <div className="w-11 h-11 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center">
              <Users className="w-5 h-5" />
            </div>
            <div>
              <p className="text-2xl font-bold text-foreground leading-none">
                {opdPatients.length}
              </p>
              <p className="text-xs text-muted-foreground mt-0.5">OPD</p>
            </div>
          </CardContent>
        </Card>
        <Card className="border-0 shadow-sm">
          <CardContent className="pt-5 pb-4 px-5 flex items-center gap-4">
            <div className="relative w-11 h-11">
              <div className="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                <FileText className="w-5 h-5" />
              </div>
              {pendingDrafts.length > 0 && (
                <span className="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center leading-none">
                  {pendingDrafts.length}
                </span>
              )}
            </div>
            <div>
              <p className="text-2xl font-bold text-foreground leading-none">
                {pendingDrafts.length}
              </p>
              <p className="text-xs text-muted-foreground mt-0.5">
                Pending Approvals
              </p>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid lg:grid-cols-2 gap-4">
        {/* Patient list with filter tabs */}
        <Card>
          <CardHeader className="pb-3 pt-4 px-5 flex flex-row items-center justify-between">
            <div className="flex items-center gap-2">
              <h2 className="font-semibold text-foreground text-sm">
                Patients
              </h2>
            </div>
            <div className="flex items-center gap-1">
              <div className="flex border border-border rounded-lg overflow-hidden text-xs">
                <button
                  type="button"
                  onClick={() => setPatientFilter("all")}
                  className={`px-2.5 py-1 font-medium transition-colors ${patientFilter === "all" ? "bg-primary text-primary-foreground" : "bg-card text-muted-foreground hover:bg-muted"}`}
                  data-ocid="mo.filter.all_tab"
                >
                  All ({allPatients.length})
                </button>
                <button
                  type="button"
                  onClick={() => setPatientFilter("admitted")}
                  className={`px-2.5 py-1 font-medium transition-colors border-l border-border ${patientFilter === "admitted" ? "bg-primary text-primary-foreground" : "bg-card text-muted-foreground hover:bg-muted"}`}
                  data-ocid="mo.filter.admitted_tab"
                >
                  Admitted ({admittedPatients.length})
                </button>
              </div>
              <Button
                variant="ghost"
                size="sm"
                className="text-xs gap-1 ml-1"
                onClick={() => navigate({ to: "/Patients" })}
              >
                <ArrowRight className="w-3 h-3" />
              </Button>
            </div>
          </CardHeader>
          <CardContent className="px-5 pb-4 space-y-2">
            {displayedPatients.length === 0 ? (
              <div
                className="text-center py-8 text-muted-foreground"
                data-ocid="mo.patients.empty_state"
              >
                <BedDouble className="w-8 h-8 mx-auto mb-2 opacity-30" />
                <p className="text-sm">
                  {patientFilter === "admitted"
                    ? "No admitted patients"
                    : "No patients yet"}
                </p>
              </div>
            ) : (
              displayedPatients.slice(0, 6).map((p) => (
                <div
                  key={String(p.id)}
                  className="border border-border rounded-xl p-3 flex items-center gap-3"
                  data-ocid={`mo.patient_card.${String(p.id)}`}
                >
                  <div
                    className={`w-9 h-9 rounded-full flex items-center justify-center shrink-0 ${isAdmitted(p) ? "bg-green-100" : "bg-sky-100"}`}
                  >
                    <span
                      className={`font-bold text-sm ${isAdmitted(p) ? "text-green-700" : "text-sky-700"}`}
                    >
                      {p.fullName.charAt(0)}
                    </span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <p className="font-semibold text-sm text-foreground truncate">
                        {p.fullName}
                      </p>
                      {isAdmitted(p) && (
                        <Badge className="text-[10px] bg-green-100 text-green-800 border border-green-300 shrink-0">
                          🏥 Admitted
                        </Badge>
                      )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {isAdmitted(p)
                        ? `Bed ${p.bedNumber || "—"} · ${p.ward || "General"}`
                        : "OPD Patient"}
                    </p>
                  </div>
                  <div className="flex gap-1">
                    <Button
                      size="sm"
                      variant="outline"
                      className="text-xs h-7 px-2 gap-1 text-green-700 border-green-200 hover:bg-green-50"
                      onClick={() =>
                        navigate({
                          to: "/PatientProfile",
                          search: { id: String(p.id) },
                        })
                      }
                      data-ocid="mo.add_note.button"
                    >
                      <PlusCircle className="w-3 h-3" /> Note
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      className="text-xs h-7 px-2 gap-1"
                      onClick={() =>
                        navigate({
                          to: "/PatientProfile",
                          search: { id: String(p.id) },
                        })
                      }
                      data-ocid="mo.view_patient.button"
                    >
                      View
                    </Button>
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        {/* Right column */}
        <div className="space-y-4">
          {/* Pending drafts — collapsible with badge */}
          <Card
            className={
              pendingDrafts.length > 0 ? "border-red-300" : "border-amber-200"
            }
          >
            <CardHeader className="pb-3 pt-4 px-5">
              <button
                type="button"
                className="w-full flex items-center justify-between gap-2"
                onClick={() => {
                  setDraftsExpanded((v) => !v);
                  if (!draftsExpanded) setPendingDrafts(loadPendingDrafts());
                }}
                data-ocid="mo.pending_approvals.toggle"
              >
                <div className="flex items-center gap-2">
                  <ClipboardList className="w-4 h-4 text-amber-600" />
                  <h2 className="font-semibold text-foreground text-sm">
                    Prescriptions Awaiting Approval
                  </h2>
                  {pendingDrafts.length > 0 && (
                    <span
                      className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-600 text-white text-[10px] font-bold leading-none"
                      data-ocid="mo.pending_approvals.badge"
                    >
                      {pendingDrafts.length}
                    </span>
                  )}
                </div>
                {draftsExpanded ? (
                  <ChevronUp className="w-4 h-4 text-muted-foreground" />
                ) : (
                  <ChevronDown className="w-4 h-4 text-muted-foreground" />
                )}
              </button>
            </CardHeader>
            {draftsExpanded && (
              <CardContent className="px-5 pb-4">
                {pendingDrafts.length === 0 ? (
                  <div
                    className="flex items-center gap-2 text-emerald-600 py-2"
                    data-ocid="mo.drafts.empty_state"
                  >
                    <CheckCircle2 className="w-4 h-4" />
                    <p className="text-sm">All prescriptions approved</p>
                  </div>
                ) : (
                  <div className="space-y-2">
                    {pendingDrafts.slice(0, 5).map((d, idx) => (
                      <div
                        key={d.id}
                        className="flex items-center gap-2 bg-red-50 rounded-lg px-3 py-2 border border-red-200"
                        data-ocid={`mo.pending_approval.item.${idx + 1}`}
                      >
                        <Loader2 className="w-3.5 h-3.5 text-amber-600 shrink-0" />
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium text-foreground truncate">
                            {d.patientName}
                          </p>
                          <p className="text-xs text-muted-foreground truncate">
                            By {d.internName} ·{" "}
                            {d.createdAt
                              ? new Date(d.createdAt).toLocaleDateString()
                              : "—"}
                          </p>
                        </div>
                        <Button
                          size="sm"
                          className="h-7 px-2 text-xs bg-red-600 hover:bg-red-700 text-white gap-1 shrink-0"
                          onClick={() =>
                            navigate({
                              to: "/PatientProfile",
                              search: { id: d.patientId },
                            })
                          }
                          data-ocid={`mo.pending_approval.review.${idx + 1}`}
                        >
                          <Eye className="w-3 h-3" /> Review
                        </Button>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            )}
          </Card>

          {/* Recent activity */}
          <Card>
            <CardHeader className="pb-3 pt-4 px-5">
              <div className="flex items-center gap-2">
                <Activity className="w-4 h-4 text-primary" />
                <h2 className="font-semibold text-foreground text-sm">
                  Recent Activity
                </h2>
              </div>
            </CardHeader>
            <CardContent className="px-5 pb-4">
              {recentActivity.length === 0 ? (
                <p
                  className="text-sm text-muted-foreground py-2"
                  data-ocid="mo.activity.empty_state"
                >
                  No recent activity
                </p>
              ) : (
                <div className="space-y-2">
                  {recentActivity.slice(0, 5).map((entry, i) => (
                    <div
                      key={`${entry.timestamp}-${i}`}
                      className="text-xs text-muted-foreground flex items-start gap-2 py-1 border-b border-border last:border-0"
                    >
                      <span className="font-medium text-foreground min-w-0 truncate">
                        {entry.userName}
                      </span>
                      <span className="shrink-0">{entry.action}</span>
                      <span className="ml-auto shrink-0 flex items-center gap-1">
                        <Clock className="w-3 h-3" />
                        {new Date(entry.timestamp).toLocaleTimeString([], {
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
