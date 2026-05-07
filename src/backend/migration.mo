import Map "mo:core/Map";
import Principal "mo:core/Principal";

import NewTypes "types/clinical-data-engine";

module {

  // ─── Old Types (inlined from .old/src/backend/types/clinical-data-engine.mo) ──

  type StaffRole = {
    #admin;
    #doctor;
    #consultant_doctor;
    #medical_officer;
    #intern_doctor;
    #nurse;
    #staff;
    #patient;
  };

  type VersionedRecord = {
    version : Nat;
    createdAt : Int;
    createdBy : Principal;
    createdByName : Text;
    createdByRole : StaffRole;
    changeReason : ?Text;
  };

  type DailyProgressType = { #morning; #evening; #emergency };

  type OldDailyProgressNote = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    progressType : DailyProgressType;
    noteDate : Text;
    subjectiveComplaints : [Text];
    systemReview : ?Text;
    objectiveVitals : ?Text;
    intakeOutput : ?Text;
    drainMonitoring : ?Text;
    investigations : [Text];
    assessmentText : Text;
    planText : Text;
    activeComplaints : [Text];
    activeDiagnoses : [Text];
    authorId : Principal;
    authorName : Text;
    authorRole : StaffRole;
    isDraft : Bool;
    createdAt : Int;
    updatedAt : Int;
    versionInfo : VersionedRecord;
    previousVersionIds : [Nat];
    isDeleted : Bool;
  };

  // All other types are identical between old and new versions.
  // We only need to redeclare DailyProgressNote since that is the changed type.

  type EncounterType = { #OPD; #IPD; #Emergency; #FollowUp };
  type EncounterStatus = { #Planned; #InProgress; #Completed; #Cancelled };
  type Encounter = {
    id : Nat; patientId : Nat; encounterId : Text; encounterType : EncounterType;
    status : EncounterStatus; startDate : Int; endDate : ?Int; providerId : Principal;
    providerName : Text; locationNotes : ?Text; versionInfo : VersionedRecord;
    previousVersions : [VersionedRecord];
  };

  type ObservationType = { #Vital; #Lab; #ExamFinding; #IntakeOutput; #DrainMonitoring };
  type ObservationStatus = { #Preliminary; #Final; #Corrected };
  type Observation = {
    id : Nat; patientId : Nat; encounterId : ?Nat; observationType : ObservationType;
    code : Text; value : Text; numericValue : ?Float; unit : Text; interpretation : ?Text;
    normalRange : ?Text; status : ObservationStatus; observationDate : Int;
    recordedBy : Principal; recordedByName : Text; recordedByRole : StaffRole;
    versionInfo : VersionedRecord; isDeleted : Bool;
  };

  type OrderType = { #Medication; #LabTest; #Procedure; #Investigation };
  type OrderStatus = { #Requested; #Pending; #InProgress; #Completed; #Cancelled };
  type ClinicalOrder = {
    id : Nat; patientId : Nat; encounterId : ?Nat; orderType : OrderType;
    code : Text; description : Text; status : OrderStatus; orderedAt : Int;
    orderedBy : Principal; orderedByName : Text; orderedByRole : StaffRole;
    completedAt : ?Int; result : ?Text; notes : ?Text; versionInfo : VersionedRecord;
  };

  type NoteType = { #SOAP; #DailyProgress; #Discharge; #Nursing; #Handover; #General };
  type ClinicalNote = {
    id : Nat; patientId : Nat; encounterId : ?Nat; noteType : NoteType;
    noteSubtype : ?Text; authorId : Principal; authorName : Text; authorRole : StaffRole;
    content : Text; isDraft : Bool; createdAt : Int; versionInfo : VersionedRecord;
    previousVersionIds : [Nat]; isDeleted : Bool;
  };

  type AuditEntry = {
    id : Nat; entityType : Text; entityId : Nat; fieldName : Text;
    beforeValue : ?Text; afterValue : Text; changedBy : Principal;
    changedByName : Text; changedByRole : StaffRole; changedAt : Int;
    reason : ?Text; ipAddress : ?Text;
  };

  type OldMedication = {
    name : Text; dose : Text; route : Text; frequency : Text;
    duration : Text; instructions : ?Text; isPRN : Bool; prnCondition : ?Text;
  };
  type MedicationAdministrationStatus = { #Given; #NotGiven; #Delayed };
  type MedicationAdministration = {
    id : Nat; medicationName : Text; patientId : Nat; dose : Text;
    scheduledTime : Int; administeredAt : ?Int; status : MedicationAdministrationStatus;
    missedReason : ?Text; recordedBy : Text; recordedByRole : Text; createdAt : Int;
  };

  // Old Prescription from clinical-data-engine (not the main.mo one)
  type OldClinicalPrescription = {
    id : Nat; patientId : Nat; encounterId : ?Nat; medications : [OldMedication];
    diagnoses : [Text]; advice : [Text]; followUpDate : ?Int;
    followUpCreatesAppointment : Bool; isDraft : Bool; isFinalized : Bool;
    authorId : Principal; authorName : Text; authorRole : StaffRole;
    createdAt : Int; updatedAt : Int; versionInfo : VersionedRecord; isDeleted : Bool;
  };

  type AlertType = {
    #Sepsis; #AKI; #Hypotension; #Hypoxia; #DrugInteraction;
    #AllergyContraindication; #CriticalLab; #MissedDoseEscalation;
  };
  type AlertSeverity = { #Critical; #Warning; #Info };
  type ClinicalAlert = {
    id : Nat; patientId : Nat; alertType : AlertType; severity : AlertSeverity;
    message : Text; details : ?Text; triggeredAt : Int; triggeredBy : Text;
    isAcknowledged : Bool; acknowledgedBy : ?Principal; acknowledgedAt : ?Int;
    isResolved : Bool; resolvedAt : ?Int;
  };

  type BedStatus = { #Empty; #Occupied; #Maintenance };
  type BedTransferEntry = { fromBed : Text; toBed : Text; date : Int; reason : Text };
  type BedRecord = {
    id : Nat; bedNumber : Text; ward : Text; status : BedStatus;
    patientId : ?Nat; patientName : ?Text; admissionDate : ?Int;
    dischargeDate : ?Int; transferHistory : [BedTransferEntry];
  };

  type DiagnosisTemplate = {
    id : Nat; diagnosisName : Text; diagnosisNameBn : ?Text; icdCode : ?Text;
    defaultDrugs : [Text]; defaultInvestigations : [Text]; defaultAdvice : [Text];
    defaultAdviceBn : [Text]; createdBy : Principal; createdAt : Int; isActive : Bool;
  };

  type SyncRecord = {
    id : Nat; deviceId : Text; userId : Principal; lastSyncAt : Int;
    pendingChanges : Nat; lastEntityType : ?Text; lastEntityId : ?Nat;
  };

  type AppointmentType = { #chamber; #hospital };
  type AppointmentStatus = { #pending; #confirmed; #cancelled; #completed };
  type Appointment = {
    id : Text; patientId : ?Nat; patientName : Text; registerNumber : ?Text;
    phone : ?Text; appointmentType : AppointmentType; chamberName : ?Text;
    hospitalName : ?Text; date : Text; timeSlot : ?Text; status : AppointmentStatus;
    doctorEmail : Text; serialNumber : ?Nat; notes : ?Text; createdAt : Int; updatedAt : Int;
  };

  type QueueStatus = { #waiting; #serving; #done; #skipped };
  type SerialQueueEntry = {
    id : Text; date : Text; serialNumber : Nat; patientName : Text;
    registerNumber : ?Text; phone : ?Text; status : QueueStatus;
    calledAt : ?Int; doctorEmail : Text; createdAt : Int; updatedAt : Int;
  };

  type HandoverShift = { #morning; #evening; #night };
  type HandoverStatus = { #draft; #submitted };
  type HandoverEntry = {
    id : Nat; patientId : Nat; shift : HandoverShift; shiftStartTime : Int;
    shiftEndTime : Int; status : HandoverStatus; patientName : Text;
    registerNumber : ?Text; ward : ?Text; bedNumber : ?Text; diagnosis : ?Text;
    dayOfStay : ?Nat; currentConsultant : ?Text; clinicalSummary : Text;
    vitalsSummary : ?Text; actionableItems : [Text]; tasksPending : [Text];
    pendingInvestigations : [Text]; pendingProcedures : [Text];
    missedMedications : [Text]; givenByName : Text; givenByRole : StaffRole;
    givenByPrincipal : Principal; takenByName : ?Text; takenByRole : ?StaffRole;
    takenByPrincipal : ?Principal; consultantComment : ?Text;
    consultantCommentAt : ?Int; consultantCommentBy : ?Principal;
    createdAt : Int; updatedAt : Int; versionInfo : VersionedRecord;
  };

  // ─── Old EngineState ─────────────────────────────────────────────────────────

  type OldEngineState = {
    encounters : Map.Map<Nat, Encounter>;
    observations : Map.Map<Nat, Observation>;
    orders : Map.Map<Nat, ClinicalOrder>;
    notes : Map.Map<Nat, ClinicalNote>;
    auditEntries : Map.Map<Nat, AuditEntry>;
    alerts : Map.Map<Nat, ClinicalAlert>;
    beds : Map.Map<Nat, BedRecord>;
    diagnosisTemplates : Map.Map<Nat, DiagnosisTemplate>;
    syncRecords : Map.Map<Text, SyncRecord>;
    appointments : Map.Map<Text, Appointment>;
    queueEntries : Map.Map<Text, SerialQueueEntry>;
    handovers : Map.Map<Nat, HandoverEntry>;
    dailyProgressNotes : Map.Map<Nat, OldDailyProgressNote>;
    medicationAdministrations : Map.Map<Nat, MedicationAdministration>;
    prescriptions : Map.Map<Nat, OldClinicalPrescription>;
    var encounterIdCounter : Nat;
    var observationIdCounter : Nat;
    var orderIdCounter : Nat;
    var noteIdCounter : Nat;
    var auditIdCounter : Nat;
    var alertIdCounter : Nat;
    var bedIdCounter : Nat;
    var diagnosisTemplateIdCounter : Nat;
    var syncRecordIdCounter : Nat;
    var handoverIdCounter : Nat;
    var dailyProgressNoteIdCounter : Nat;
    var medicationAdministrationIdCounter : Nat;
    var prescriptionIdCounter : Nat;
  };

  type NewEngineState = {
    encounters : Map.Map<Nat, NewTypes.Encounter>;
    observations : Map.Map<Nat, NewTypes.Observation>;
    orders : Map.Map<Nat, NewTypes.ClinicalOrder>;
    notes : Map.Map<Nat, NewTypes.ClinicalNote>;
    auditEntries : Map.Map<Nat, NewTypes.AuditEntry>;
    alerts : Map.Map<Nat, NewTypes.ClinicalAlert>;
    beds : Map.Map<Nat, NewTypes.BedRecord>;
    diagnosisTemplates : Map.Map<Nat, NewTypes.DiagnosisTemplate>;
    syncRecords : Map.Map<Text, NewTypes.SyncRecord>;
    appointments : Map.Map<Text, NewTypes.Appointment>;
    queueEntries : Map.Map<Text, NewTypes.SerialQueueEntry>;
    handovers : Map.Map<Nat, NewTypes.HandoverEntry>;
    dailyProgressNotes : Map.Map<Nat, NewTypes.DailyProgressNote>;
    medicationAdministrations : Map.Map<Nat, NewTypes.MedicationAdministration>;
    prescriptions : Map.Map<Nat, NewTypes.Prescription>;
    var encounterIdCounter : Nat;
    var observationIdCounter : Nat;
    var orderIdCounter : Nat;
    var noteIdCounter : Nat;
    var auditIdCounter : Nat;
    var alertIdCounter : Nat;
    var bedIdCounter : Nat;
    var diagnosisTemplateIdCounter : Nat;
    var syncRecordIdCounter : Nat;
    var handoverIdCounter : Nat;
    var dailyProgressNoteIdCounter : Nat;
    var medicationAdministrationIdCounter : Nat;
    var prescriptionIdCounter : Nat;
  };

  // ─── Actor-level State Types ───────────────────────────────────────────
  // The actor's top-level stable fields include clinicalEngineState.
  // The migration operates at this level.

  type OldActor = {
    clinicalEngineState : OldEngineState;
  };

  type NewActor = {
    clinicalEngineState : NewEngineState;
  };

  // ─── Migration function ─────────────────────────────────────────────────────

  public func run(old : OldActor) : NewActor {
    let ces = old.clinicalEngineState;

    // Migrate DailyProgressNote: add all new fields with sensible defaults.
    let migratedNotes = ces.dailyProgressNotes.map<Nat, OldDailyProgressNote, NewTypes.DailyProgressNote>(
      func(_id, n) {
        {
          n with
          noteState = if (n.isDraft) { #draft } else { #finalized };
          submittedByRole = null : ?NewTypes.StaffRole;
          submitTimestamp = null : ?Int;
          reviewedByMO = null : ?Text;
          reviewedByConsultant = null : ?Text;
          consultantComments = "";
          internSubjective = "";
          internObjective = "";
          moAssessment = "";
          moPlan = "";
          consultantOverrides = "";
          versionChain = [] : [Text];
          rejectionReason = null : ?Text;
        };
      }
    );

    {
      clinicalEngineState = {
        encounters = ces.encounters;
        observations = ces.observations;
        orders = ces.orders;
        notes = ces.notes;
        auditEntries = ces.auditEntries;
        alerts = ces.alerts;
        beds = ces.beds;
        diagnosisTemplates = ces.diagnosisTemplates;
        syncRecords = ces.syncRecords;
        appointments = ces.appointments;
        queueEntries = ces.queueEntries;
        handovers = ces.handovers;
        dailyProgressNotes = migratedNotes;
        medicationAdministrations = ces.medicationAdministrations;
        prescriptions = ces.prescriptions;
        var encounterIdCounter = ces.encounterIdCounter;
        var observationIdCounter = ces.observationIdCounter;
        var orderIdCounter = ces.orderIdCounter;
        var noteIdCounter = ces.noteIdCounter;
        var auditIdCounter = ces.auditIdCounter;
        var alertIdCounter = ces.alertIdCounter;
        var bedIdCounter = ces.bedIdCounter;
        var diagnosisTemplateIdCounter = ces.diagnosisTemplateIdCounter;
        var syncRecordIdCounter = ces.syncRecordIdCounter;
        var handoverIdCounter = ces.handoverIdCounter;
        var dailyProgressNoteIdCounter = ces.dailyProgressNoteIdCounter;
        var medicationAdministrationIdCounter = ces.medicationAdministrationIdCounter;
        var prescriptionIdCounter = ces.prescriptionIdCounter;
      };
    };
  };

};
