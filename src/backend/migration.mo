import Map "mo:core/Map";
import Principal "mo:core/Principal";

module {

  // ── Old types (all inline — do NOT import from Types.* for types containing StaffRole) ──

  // Old StaffRole — the subset that was stored before this upgrade
  type OldStaffRole = {
    #admin;
    #doctor;
    #consultant_doctor;
    #medical_officer;
    #intern_doctor;
    #nurse;
    #staff;
    #patient;
  };

  // New StaffRole — superset with the new roles
  type NewStaffRole = {
    #admin;
    #doctor;
    #consultant_doctor;
    #assistant_professor;
    #associate_professor;
    #professor;
    #medical_officer;
    #assistant_registrar;
    #registrar;
    #intern_doctor;
    #nurse;
    #staff;
    #patient;
  };

  type OldVersionedRecord = {
    version : Nat;
    createdAt : Int;
    createdBy : Principal;
    createdByName : Text;
    createdByRole : OldStaffRole;
    changeReason : ?Text;
  };

  type NewVersionedRecord = {
    version : Nat;
    createdAt : Int;
    createdBy : Principal;
    createdByName : Text;
    createdByRole : NewStaffRole;
    changeReason : ?Text;
  };

  // Encounter (StaffRole not embedded — uses Principal)
  type EncounterType = { #OPD; #IPD; #Emergency; #FollowUp };
  type EncounterStatus = { #Planned; #InProgress; #Completed; #Cancelled };
  type OldEncounter = {
    id : Nat;
    patientId : Nat;
    encounterId : Text;
    encounterType : EncounterType;
    status : EncounterStatus;
    startDate : Int;
    endDate : ?Int;
    providerId : Principal;
    providerName : Text;
    locationNotes : ?Text;
    versionInfo : OldVersionedRecord;
    previousVersions : [OldVersionedRecord];
  };
  type NewEncounter = {
    id : Nat;
    patientId : Nat;
    encounterId : Text;
    encounterType : EncounterType;
    status : EncounterStatus;
    startDate : Int;
    endDate : ?Int;
    providerId : Principal;
    providerName : Text;
    locationNotes : ?Text;
    versionInfo : NewVersionedRecord;
    previousVersions : [NewVersionedRecord];
  };

  // ObservationType and status
  type ObservationType = { #Vital; #Lab; #ExamFinding; #IntakeOutput; #DrainMonitoring };
  type ObservationStatus = { #Preliminary; #Final; #Corrected };

  // Old Observation (no vital verification fields)
  type OldObservation = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    observationType : ObservationType;
    code : Text;
    value : Text;
    numericValue : ?Float;
    unit : Text;
    interpretation : ?Text;
    normalRange : ?Text;
    status : ObservationStatus;
    observationDate : Int;
    recordedBy : Principal;
    recordedByName : Text;
    recordedByRole : OldStaffRole;
    versionInfo : OldVersionedRecord;
    isDeleted : Bool;
  };

  // New Observation (with vital verification fields)
  type VitalVerificationStatus = {
    #drafted;
    #pendingMOReview;
    #verifiedByMO;
    #finalized;
    #rejected;
  };

  type NewObservation = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    observationType : ObservationType;
    code : Text;
    value : Text;
    numericValue : ?Float;
    unit : Text;
    interpretation : ?Text;
    normalRange : ?Text;
    status : ObservationStatus;
    vitalVerificationStatus : ?VitalVerificationStatus;
    enteredBy : ?Principal;
    enteredByRole : ?NewStaffRole;
    verifiedBy : ?Principal;
    verifiedAt : ?Int;
    rejectionReason : ?Text;
    observationDate : Int;
    recordedBy : Principal;
    recordedByName : Text;
    recordedByRole : NewStaffRole;
    versionInfo : NewVersionedRecord;
    isDeleted : Bool;
  };

  // ClinicalOrder
  type OrderType = { #Medication; #LabTest; #Procedure; #Investigation };
  type OrderStatus = { #Requested; #Pending; #InProgress; #Completed; #Cancelled };
  type OldClinicalOrder = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    orderType : OrderType;
    code : Text;
    description : Text;
    status : OrderStatus;
    orderedAt : Int;
    orderedBy : Principal;
    orderedByName : Text;
    orderedByRole : OldStaffRole;
    completedAt : ?Int;
    result : ?Text;
    notes : ?Text;
    versionInfo : OldVersionedRecord;
  };
  type NewClinicalOrder = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    orderType : OrderType;
    code : Text;
    description : Text;
    status : OrderStatus;
    orderedAt : Int;
    orderedBy : Principal;
    orderedByName : Text;
    orderedByRole : NewStaffRole;
    completedAt : ?Int;
    result : ?Text;
    notes : ?Text;
    versionInfo : NewVersionedRecord;
  };

  // ClinicalNote
  type NoteType = { #SOAP; #DailyProgress; #Discharge; #Nursing; #Handover; #General };
  type OldClinicalNote = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    noteType : NoteType;
    noteSubtype : ?Text;
    authorId : Principal;
    authorName : Text;
    authorRole : OldStaffRole;
    content : Text;
    isDraft : Bool;
    createdAt : Int;
    versionInfo : OldVersionedRecord;
    previousVersionIds : [Nat];
    isDeleted : Bool;
  };
  type NewClinicalNote = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    noteType : NoteType;
    noteSubtype : ?Text;
    authorId : Principal;
    authorName : Text;
    authorRole : NewStaffRole;
    content : Text;
    isDraft : Bool;
    createdAt : Int;
    versionInfo : NewVersionedRecord;
    previousVersionIds : [Nat];
    isDeleted : Bool;
  };

  // AuditEntry
  type OldAuditEntry = {
    id : Nat;
    entityType : Text;
    entityId : Nat;
    fieldName : Text;
    beforeValue : ?Text;
    afterValue : Text;
    changedBy : Principal;
    changedByName : Text;
    changedByRole : OldStaffRole;
    changedAt : Int;
    reason : ?Text;
    ipAddress : ?Text;
  };
  type NewAuditEntry = {
    id : Nat;
    entityType : Text;
    entityId : Nat;
    fieldName : Text;
    beforeValue : ?Text;
    afterValue : Text;
    changedBy : Principal;
    changedByName : Text;
    changedByRole : NewStaffRole;
    changedAt : Int;
    reason : ?Text;
    ipAddress : ?Text;
  };

  // ClinicalAlert (no StaffRole inside)
  type AlertSeverity = { #Critical; #Warning; #Info };
  type AlertType = {
    #Sepsis; #AKI; #Hypotension; #Hypoxia; #DrugInteraction;
    #AllergyContraindication; #CriticalLab; #MissedDoseEscalation;
  };
  type SharedClinicalAlert = {
    id : Nat;
    patientId : Nat;
    alertType : AlertType;
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

  // BedRecord (no StaffRole)
  type BedStatus = { #Empty; #Occupied; #Maintenance };
  type BedTransferEntry = { fromBed : Text; toBed : Text; date : Int; reason : Text };
  type SharedBedRecord = {
    id : Nat;
    bedNumber : Text;
    ward : Text;
    status : BedStatus;
    patientId : ?Nat;
    patientName : ?Text;
    admissionDate : ?Int;
    dischargeDate : ?Int;
    transferHistory : [BedTransferEntry];
    updatedAt : Int;
  };

  // DiagnosisTemplate (no StaffRole)
  type SharedDiagnosisTemplate = {
    id : Nat;
    diagnosisName : Text;
    diagnosisNameBn : ?Text;
    icdCode : ?Text;
    defaultDrugs : [Text];
    defaultInvestigations : [Text];
    defaultAdvice : [Text];
    defaultAdviceBn : [Text];
    createdBy : Principal;
    createdAt : Int;
    isActive : Bool;
  };

  // SyncRecord (no StaffRole)
  type SharedSyncRecord = {
    id : Nat;
    deviceId : Text;
    userId : Principal;
    lastSyncAt : Int;
    pendingChanges : Nat;
    lastEntityType : ?Text;
    lastEntityId : ?Nat;
  };

  // Appointment (no StaffRole)
  type AppointmentType = { #chamber; #hospital };
  type AppointmentStatus = { #pending; #confirmed; #cancelled; #completed };
  type SharedAppointment = {
    id : Text;
    patientId : ?Nat;
    patientName : Text;
    registerNumber : ?Text;
    phone : ?Text;
    appointmentType : AppointmentType;
    chamberName : ?Text;
    hospitalName : ?Text;
    date : Text;
    timeSlot : ?Text;
    status : AppointmentStatus;
    doctorEmail : Text;
    serialNumber : ?Nat;
    notes : ?Text;
    createdAt : Int;
    updatedAt : Int;
  };

  // SerialQueueEntry (no StaffRole)
  type QueueStatus = { #waiting; #serving; #done; #skipped };
  type SharedSerialQueueEntry = {
    id : Text;
    date : Text;
    serialNumber : Nat;
    patientName : Text;
    registerNumber : ?Text;
    phone : ?Text;
    status : QueueStatus;
    calledAt : ?Int;
    doctorEmail : Text;
    createdAt : Int;
    updatedAt : Int;
  };

  // HandoverEntry (contains StaffRole)
  type HandoverShift = { #morning; #evening; #night };
  type HandoverStatus = { #draft; #submitted };
  type OldHandoverEntry = {
    id : Nat;
    patientId : Nat;
    shift : HandoverShift;
    shiftStartTime : Int;
    shiftEndTime : Int;
    status : HandoverStatus;
    patientName : Text;
    registerNumber : ?Text;
    ward : ?Text;
    bedNumber : ?Text;
    diagnosis : ?Text;
    dayOfStay : ?Nat;
    currentConsultant : ?Text;
    clinicalSummary : Text;
    vitalsSummary : ?Text;
    actionableItems : [Text];
    tasksPending : [Text];
    pendingInvestigations : [Text];
    pendingProcedures : [Text];
    missedMedications : [Text];
    givenByName : Text;
    givenByRole : OldStaffRole;
    givenByPrincipal : Principal;
    takenByName : ?Text;
    takenByRole : ?OldStaffRole;
    takenByPrincipal : ?Principal;
    consultantComment : ?Text;
    consultantCommentAt : ?Int;
    consultantCommentBy : ?Principal;
    createdAt : Int;
    updatedAt : Int;
    versionInfo : OldVersionedRecord;
  };
  type NewHandoverEntry = {
    id : Nat;
    patientId : Nat;
    shift : HandoverShift;
    shiftStartTime : Int;
    shiftEndTime : Int;
    status : HandoverStatus;
    patientName : Text;
    registerNumber : ?Text;
    ward : ?Text;
    bedNumber : ?Text;
    diagnosis : ?Text;
    dayOfStay : ?Nat;
    currentConsultant : ?Text;
    clinicalSummary : Text;
    vitalsSummary : ?Text;
    actionableItems : [Text];
    tasksPending : [Text];
    pendingInvestigations : [Text];
    pendingProcedures : [Text];
    missedMedications : [Text];
    givenByName : Text;
    givenByRole : NewStaffRole;
    givenByPrincipal : Principal;
    takenByName : ?Text;
    takenByRole : ?NewStaffRole;
    takenByPrincipal : ?Principal;
    consultantComment : ?Text;
    consultantCommentAt : ?Int;
    consultantCommentBy : ?Principal;
    createdAt : Int;
    updatedAt : Int;
    versionInfo : NewVersionedRecord;
  };

  // DailyProgressNote (contains StaffRole)
  type DailyProgressType = { #morning; #evening; #emergency };
  type DailyNoteState = { #draft; #submittedToMO; #moReviewComplete; #finalized; #rejected };
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
    noteState : DailyNoteState;
    submittedByRole : ?OldStaffRole;
    submitTimestamp : ?Int;
    reviewedByMO : ?Text;
    reviewedByConsultant : ?Text;
    consultantComments : Text;
    internSubjective : Text;
    internObjective : Text;
    moAssessment : Text;
    moPlan : Text;
    consultantOverrides : Text;
    versionChain : [Text];
    rejectionReason : ?Text;
    authorId : Principal;
    authorName : Text;
    authorRole : OldStaffRole;
    isDraft : Bool;
    createdAt : Int;
    updatedAt : Int;
    versionInfo : OldVersionedRecord;
    previousVersionIds : [Nat];
    isDeleted : Bool;
  };
  type NewDailyProgressNote = {
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
    noteState : DailyNoteState;
    submittedByRole : ?NewStaffRole;
    submitTimestamp : ?Int;
    reviewedByMO : ?Text;
    reviewedByConsultant : ?Text;
    consultantComments : Text;
    internSubjective : Text;
    internObjective : Text;
    moAssessment : Text;
    moPlan : Text;
    consultantOverrides : Text;
    versionChain : [Text];
    rejectionReason : ?Text;
    authorId : Principal;
    authorName : Text;
    authorRole : NewStaffRole;
    isDraft : Bool;
    createdAt : Int;
    updatedAt : Int;
    versionInfo : NewVersionedRecord;
    previousVersionIds : [Nat];
    isDeleted : Bool;
  };

  // MedicationAdministration (no StaffRole — uses Text for role)
  type MedicationAdministrationStatus = { #Given; #NotGiven; #Delayed };
  type SharedMedicationAdministration = {
    id : Nat;
    medicationName : Text;
    patientId : Nat;
    dose : Text;
    scheduledTime : Int;
    administeredAt : ?Int;
    status : MedicationAdministrationStatus;
    missedReason : ?Text;
    recordedBy : Text;
    recordedByRole : Text;
    createdAt : Int;
    updatedAt : Int;
  };

  // Prescription (contains StaffRole)
  type OldMedication = {
    name : Text;
    dose : Text;
    route : Text;
    frequency : Text;
    duration : Text;
    instructions : ?Text;
    isPRN : Bool;
    prnCondition : ?Text;
  };
  type OldPrescription = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    medications : [OldMedication];
    diagnoses : [Text];
    advice : [Text];
    followUpDate : ?Int;
    followUpCreatesAppointment : Bool;
    isDraft : Bool;
    isFinalized : Bool;
    authorId : Principal;
    authorName : Text;
    authorRole : OldStaffRole;
    createdAt : Int;
    updatedAt : Int;
    versionInfo : OldVersionedRecord;
    isDeleted : Bool;
  };
  type NewPrescription = {
    id : Nat;
    patientId : Nat;
    encounterId : ?Nat;
    medications : [OldMedication];  // Medication shape unchanged
    diagnoses : [Text];
    advice : [Text];
    followUpDate : ?Int;
    followUpCreatesAppointment : Bool;
    isDraft : Bool;
    isFinalized : Bool;
    authorId : Principal;
    authorName : Text;
    authorRole : NewStaffRole;
    createdAt : Int;
    updatedAt : Int;
    versionInfo : NewVersionedRecord;
    isDeleted : Bool;
  };

  // New types (admission/role change)
  type AdmissionStatus = { #admitted; #discharged; #transferred };
  type NewAdmissionRecord = {
    id : Nat;
    patientId : Nat;
    consultantEmail : Text;
    bed : Text;
    ward : Text;
    department : Text;
    status : AdmissionStatus;
    admittedAt : Int;
    dischargedAt : ?Int;
    admittedBy : Principal;
    admittedByRole : NewStaffRole;
    updatedAt : Int;
  };

  type NewRoleChangeEntry = {
    id : Nat;
    principal : Principal;
    previousRole : ?NewStaffRole;
    newRole : NewStaffRole;
    changedBy : Principal;
    timestamp : Int;
  };

  // ── Old actor state ───────────────────────────────────────────────────

  type OldActor = {
    clinicalEngineState : {
      encounters : Map.Map<Nat, OldEncounter>;
      observations : Map.Map<Nat, OldObservation>;
      orders : Map.Map<Nat, OldClinicalOrder>;
      notes : Map.Map<Nat, OldClinicalNote>;
      auditEntries : Map.Map<Nat, OldAuditEntry>;
      alerts : Map.Map<Nat, SharedClinicalAlert>;
      beds : Map.Map<Nat, SharedBedRecord>;
      diagnosisTemplates : Map.Map<Nat, SharedDiagnosisTemplate>;
      syncRecords : Map.Map<Text, SharedSyncRecord>;
      appointments : Map.Map<Text, SharedAppointment>;
      queueEntries : Map.Map<Text, SharedSerialQueueEntry>;
      handovers : Map.Map<Nat, OldHandoverEntry>;
      dailyProgressNotes : Map.Map<Nat, OldDailyProgressNote>;
      medicationAdministrations : Map.Map<Nat, SharedMedicationAdministration>;
      prescriptions : Map.Map<Nat, OldPrescription>;
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
  };

  // ── New actor state ───────────────────────────────────────────────────

  type NewActor = {
    clinicalEngineState : {
      encounters : Map.Map<Nat, NewEncounter>;
      observations : Map.Map<Nat, NewObservation>;
      orders : Map.Map<Nat, NewClinicalOrder>;
      notes : Map.Map<Nat, NewClinicalNote>;
      auditEntries : Map.Map<Nat, NewAuditEntry>;
      alerts : Map.Map<Nat, SharedClinicalAlert>;
      beds : Map.Map<Nat, SharedBedRecord>;
      diagnosisTemplates : Map.Map<Nat, SharedDiagnosisTemplate>;
      syncRecords : Map.Map<Text, SharedSyncRecord>;
      appointments : Map.Map<Text, SharedAppointment>;
      queueEntries : Map.Map<Text, SharedSerialQueueEntry>;
      handovers : Map.Map<Nat, NewHandoverEntry>;
      dailyProgressNotes : Map.Map<Nat, NewDailyProgressNote>;
      medicationAdministrations : Map.Map<Nat, SharedMedicationAdministration>;
      prescriptions : Map.Map<Nat, NewPrescription>;
      admissions : Map.Map<Nat, NewAdmissionRecord>;
      roleChangeLog : Map.Map<Nat, NewRoleChangeEntry>;
      emailIndex : Map.Map<Text, Principal>;
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
      var admissionIdCounter : Nat;
      var roleChangeIdCounter : Nat;
    };
  };

  // ── Per-record upgrade helpers ───────────────────────────────────────────

  func upgradeVersionedRecord(old : OldVersionedRecord) : NewVersionedRecord {
    { old with createdByRole = old.createdByRole };
  };

  func upgradeEncounter(old : OldEncounter) : NewEncounter {
    {
      old with
      versionInfo = upgradeVersionedRecord(old.versionInfo);
      previousVersions = old.previousVersions.map(upgradeVersionedRecord);
    };
  };

  func upgradeObservation(old : OldObservation) : NewObservation {
    {
      id = old.id;
      patientId = old.patientId;
      encounterId = old.encounterId;
      observationType = old.observationType;
      code = old.code;
      value = old.value;
      numericValue = old.numericValue;
      unit = old.unit;
      interpretation = old.interpretation;
      normalRange = old.normalRange;
      status = old.status;
      vitalVerificationStatus = null;
      enteredBy = ?old.recordedBy;
      enteredByRole = ?(old.recordedByRole);
      verifiedBy = null;
      verifiedAt = null;
      rejectionReason = null;
      observationDate = old.observationDate;
      recordedBy = old.recordedBy;
      recordedByName = old.recordedByName;
      recordedByRole = old.recordedByRole;
      versionInfo = upgradeVersionedRecord(old.versionInfo);
      isDeleted = old.isDeleted;
    };
  };

  func upgradeOrder(old : OldClinicalOrder) : NewClinicalOrder {
    { old with versionInfo = upgradeVersionedRecord(old.versionInfo) };
  };

  func upgradeNote(old : OldClinicalNote) : NewClinicalNote {
    { old with versionInfo = upgradeVersionedRecord(old.versionInfo) };
  };

  func upgradeAuditEntry(old : OldAuditEntry) : NewAuditEntry {
    // OldStaffRole is stable-subtype of NewStaffRole; cast directly
    { old with changedByRole = old.changedByRole };
  };

  func upgradeHandover(old : OldHandoverEntry) : NewHandoverEntry {
    { old with versionInfo = upgradeVersionedRecord(old.versionInfo) };
  };

  func upgradeProgressNote(old : OldDailyProgressNote) : NewDailyProgressNote {
    { old with versionInfo = upgradeVersionedRecord(old.versionInfo) };
  };

  func upgradePrescription(old : OldPrescription) : NewPrescription {
    { old with versionInfo = upgradeVersionedRecord(old.versionInfo) };
  };

  // ── Public migration entry point ─────────────────────────────────────────

  public func run(old : OldActor) : NewActor {
    let e = old.clinicalEngineState;
    {
      clinicalEngineState = {
        encounters     = e.encounters.map<Nat, OldEncounter, NewEncounter>(func(_, v) { upgradeEncounter(v) });
        observations   = e.observations.map<Nat, OldObservation, NewObservation>(func(_, v) { upgradeObservation(v) });
        orders         = e.orders.map<Nat, OldClinicalOrder, NewClinicalOrder>(func(_, v) { upgradeOrder(v) });
        notes          = e.notes.map<Nat, OldClinicalNote, NewClinicalNote>(func(_, v) { upgradeNote(v) });
        auditEntries   = e.auditEntries.map<Nat, OldAuditEntry, NewAuditEntry>(func(_, v) { upgradeAuditEntry(v) });
        alerts         = e.alerts;
        beds           = e.beds;
        diagnosisTemplates = e.diagnosisTemplates;
        syncRecords    = e.syncRecords;
        appointments   = e.appointments;
        queueEntries   = e.queueEntries;
        handovers      = e.handovers.map<Nat, OldHandoverEntry, NewHandoverEntry>(func(_, v) { upgradeHandover(v) });
        dailyProgressNotes = e.dailyProgressNotes.map<Nat, OldDailyProgressNote, NewDailyProgressNote>(func(_, v) { upgradeProgressNote(v) });
        medicationAdministrations = e.medicationAdministrations;
        prescriptions  = e.prescriptions.map<Nat, OldPrescription, NewPrescription>(func(_, v) { upgradePrescription(v) });
        admissions     = Map.empty<Nat, NewAdmissionRecord>();
        roleChangeLog  = Map.empty<Nat, NewRoleChangeEntry>();
        emailIndex     = Map.empty<Text, Principal>();
        var encounterIdCounter               = e.encounterIdCounter;
        var observationIdCounter             = e.observationIdCounter;
        var orderIdCounter                   = e.orderIdCounter;
        var noteIdCounter                    = e.noteIdCounter;
        var auditIdCounter                   = e.auditIdCounter;
        var alertIdCounter                   = e.alertIdCounter;
        var bedIdCounter                     = e.bedIdCounter;
        var diagnosisTemplateIdCounter       = e.diagnosisTemplateIdCounter;
        var syncRecordIdCounter              = e.syncRecordIdCounter;
        var handoverIdCounter                = e.handoverIdCounter;
        var dailyProgressNoteIdCounter       = e.dailyProgressNoteIdCounter;
        var medicationAdministrationIdCounter = e.medicationAdministrationIdCounter;
        var prescriptionIdCounter            = e.prescriptionIdCounter;
        var admissionIdCounter               = 1;
        var roleChangeIdCounter              = 1;
      };
    };
  };

};
