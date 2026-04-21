// Migration: adds #MissedDoseEscalation to AlertType variant and new engine
// state fields (medicationAdministrations, prescriptions, their counters).
//
// Old AlertType had 7 constructors; new adds #MissedDoseEscalation.
// All existing ClinicalAlert records must be re-mapped so their alertType
// value is decoded as the new 8-constructor variant.

import Map "mo:core/Map";
import Principal "mo:core/Principal";

import NewTypes "types/clinical-data-engine";

module {

  // ─── Old inline type definitions (copied from .old/src/backend) ────────────

  type OldAlertType = {
    #Sepsis;
    #AKI;
    #Hypotension;
    #Hypoxia;
    #DrugInteraction;
    #AllergyContraindication;
    #CriticalLab;
  };

  type AlertSeverity = { #Critical; #Warning; #Info };

  type OldClinicalAlert = {
    id : Nat;
    patientId : Nat;
    alertType : OldAlertType;
    severity : AlertSeverity;
    message : Text;
    details : ?Text;
    triggeredAt : Int;
    triggeredBy : Text;
    isAcknowledged : Bool;
    acknowledgedBy : ?Principal;
    acknowledgedAt : ?Int;
    isResolved : Bool;
    resolvedAt : ?Int;
  };

  // Shared sub-types that are unchanged between versions (needed to build
  // the old actor record type without importing from .old/).
  type Encounter          = NewTypes.Encounter;
  type Observation        = NewTypes.Observation;
  type ClinicalOrder      = NewTypes.ClinicalOrder;
  type ClinicalNote       = NewTypes.ClinicalNote;
  type AuditEntry         = NewTypes.AuditEntry;
  type BedRecord          = NewTypes.BedRecord;
  type DiagnosisTemplate  = NewTypes.DiagnosisTemplate;
  type SyncRecord         = NewTypes.SyncRecord;
  type Appointment        = NewTypes.Appointment;
  type SerialQueueEntry   = NewTypes.SerialQueueEntry;
  type HandoverEntry      = NewTypes.HandoverEntry;
  type DailyProgressNote  = NewTypes.DailyProgressNote;

  // ─── Actor state records ────────────────────────────────────────────────────

  type OldActor = {
    clinicalEngineState : {
      encounters           : Map.Map<Nat, Encounter>;
      observations         : Map.Map<Nat, Observation>;
      orders               : Map.Map<Nat, ClinicalOrder>;
      notes                : Map.Map<Nat, ClinicalNote>;
      auditEntries         : Map.Map<Nat, AuditEntry>;
      alerts               : Map.Map<Nat, OldClinicalAlert>;
      beds                 : Map.Map<Nat, BedRecord>;
      diagnosisTemplates   : Map.Map<Nat, DiagnosisTemplate>;
      syncRecords          : Map.Map<Text, SyncRecord>;
      appointments         : Map.Map<Text, Appointment>;
      queueEntries         : Map.Map<Text, SerialQueueEntry>;
      handovers            : Map.Map<Nat, HandoverEntry>;
      dailyProgressNotes   : Map.Map<Nat, DailyProgressNote>;
      var encounterIdCounter           : Nat;
      var observationIdCounter         : Nat;
      var orderIdCounter               : Nat;
      var noteIdCounter                : Nat;
      var auditIdCounter               : Nat;
      var alertIdCounter               : Nat;
      var bedIdCounter                 : Nat;
      var diagnosisTemplateIdCounter   : Nat;
      var syncRecordIdCounter          : Nat;
      var handoverIdCounter            : Nat;
      var dailyProgressNoteIdCounter   : Nat;
    };
  };

  type NewAlert = NewTypes.ClinicalAlert;

  type NewEngineState = {
    encounters           : Map.Map<Nat, Encounter>;
    observations         : Map.Map<Nat, Observation>;
    orders               : Map.Map<Nat, ClinicalOrder>;
    notes                : Map.Map<Nat, ClinicalNote>;
    auditEntries         : Map.Map<Nat, AuditEntry>;
    alerts               : Map.Map<Nat, NewAlert>;
    beds                 : Map.Map<Nat, BedRecord>;
    diagnosisTemplates   : Map.Map<Nat, DiagnosisTemplate>;
    syncRecords          : Map.Map<Text, SyncRecord>;
    appointments         : Map.Map<Text, Appointment>;
    queueEntries         : Map.Map<Text, SerialQueueEntry>;
    handovers            : Map.Map<Nat, HandoverEntry>;
    dailyProgressNotes   : Map.Map<Nat, DailyProgressNote>;
    medicationAdministrations : Map.Map<Nat, NewTypes.MedicationAdministration>;
    prescriptions             : Map.Map<Nat, NewTypes.Prescription>;
    var encounterIdCounter               : Nat;
    var observationIdCounter             : Nat;
    var orderIdCounter                   : Nat;
    var noteIdCounter                    : Nat;
    var auditIdCounter                   : Nat;
    var alertIdCounter                   : Nat;
    var bedIdCounter                     : Nat;
    var diagnosisTemplateIdCounter       : Nat;
    var syncRecordIdCounter              : Nat;
    var handoverIdCounter                : Nat;
    var dailyProgressNoteIdCounter       : Nat;
    var medicationAdministrationIdCounter : Nat;
    var prescriptionIdCounter            : Nat;
  };

  type NewActor = {
    clinicalEngineState : NewEngineState;
  };

  // ─── Helper: cast old AlertType to new AlertType ───────────────────────────

  func upgradeAlertType(old : OldAlertType) : NewTypes.AlertType {
    switch (old) {
      case (#Sepsis)                #Sepsis;
      case (#AKI)                   #AKI;
      case (#Hypotension)           #Hypotension;
      case (#Hypoxia)               #Hypoxia;
      case (#DrugInteraction)       #DrugInteraction;
      case (#AllergyContraindication) #AllergyContraindication;
      case (#CriticalLab)           #CriticalLab;
    };
  };

  func upgradeAlert(old : OldClinicalAlert) : NewAlert {
    {
      old with
      alertType = upgradeAlertType(old.alertType);
    };
  };

  // ─── Migration entry point ─────────────────────────────────────────────────

  public func run(old : OldActor) : NewActor {
    let es = old.clinicalEngineState;

    let newAlerts = es.alerts.map<Nat, OldClinicalAlert, NewAlert>(
      func(_id, alert) { upgradeAlert(alert) }
    );

    let newEngineState : NewEngineState = {
      encounters           = es.encounters;
      observations         = es.observations;
      orders               = es.orders;
      notes                = es.notes;
      auditEntries         = es.auditEntries;
      alerts               = newAlerts;
      beds                 = es.beds;
      diagnosisTemplates   = es.diagnosisTemplates;
      syncRecords          = es.syncRecords;
      appointments         = es.appointments;
      queueEntries         = es.queueEntries;
      handovers            = es.handovers;
      dailyProgressNotes   = es.dailyProgressNotes;
      medicationAdministrations = Map.empty<Nat, NewTypes.MedicationAdministration>();
      prescriptions             = Map.empty<Nat, NewTypes.Prescription>();
      var encounterIdCounter               = es.encounterIdCounter;
      var observationIdCounter             = es.observationIdCounter;
      var orderIdCounter                   = es.orderIdCounter;
      var noteIdCounter                    = es.noteIdCounter;
      var auditIdCounter                   = es.auditIdCounter;
      var alertIdCounter                   = es.alertIdCounter;
      var bedIdCounter                     = es.bedIdCounter;
      var diagnosisTemplateIdCounter       = es.diagnosisTemplateIdCounter;
      var syncRecordIdCounter              = es.syncRecordIdCounter;
      var handoverIdCounter                = es.handoverIdCounter;
      var dailyProgressNoteIdCounter       = es.dailyProgressNoteIdCounter;
      var medicationAdministrationIdCounter = 1;
      var prescriptionIdCounter            = 1;
    };

    { clinicalEngineState = newEngineState };
  };
};
