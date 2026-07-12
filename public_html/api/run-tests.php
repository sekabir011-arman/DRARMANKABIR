<?php
/**
 * Dr. Arman Kabir Care - End-to-End Test Suite
 * 
 * Run via: curl https://drarmankabir.com/api/run-tests.php
 */

header('Content-Type: text/plain');
error_reporting(E_ALL);

// Disable time limit for tests
set_time_limit(120);

$base = 'https://drarmankabir.com';
$pass = 0;
$fail = 0;
$tests = [];

function test(string $name, string $method, string $path, array $data = null, string $token = null, int $expectedHttp = 200): array {
    $url = "https://drarmankabir.com" . $path;
    
    $headers = ["Content-Type: application/json"];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ];
    
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($data) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    } elseif ($method === 'GET') {
        if ($data) {
            $url .= '?' . http_build_query($data);
            $opts[CURLOPT_URL] = $url;
        }
    }
    
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $json = json_decode($resp, true);
    
    return [
        'name' => $name,
        'http' => $httpCode,
        'success' => ($json['success'] ?? false) === true,
        'message' => $json['message'] ?? $json['error'] ?? 'Unknown',
        'data' => $json['data'] ?? null,
        'http_ok' => $httpCode === $expectedHttp,
    ];
}

function assertTest(string $name, array $result): void {
    global $pass, $fail, $tests;
    $ok = $result['http_ok'] && $result['success'];
    if ($ok) {
        echo "  ✅ $name\n";
        $pass++;
    } else {
        echo "  ❌ $name\n";
        echo "     HTTP: {$result['http']} | Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
        echo "     Message: {$result['message']}\n";
        $fail++;
    }
    $tests[] = $result;
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  Dr. Arman Kabir Care - End-to-End Test Suite\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// ─── AUTH TESTS ──────────────────────────────────────────────────────────
echo "─── Authentication ───────────────────────────────────────\n";

// 1.1 Login with valid credentials
$login = test('Admin Login', 'POST', '/api/auth/login.php', [
    'email' => 'admin@drarmankabir.com',
    'password' => 'admin123'
]);
assertTest('Login with valid credentials', $login);
$token = $login['data']['token'] ?? '';

// 1.2 Login with wrong password
$badLogin = test('Invalid Password', 'POST', '/api/auth/login.php', [
    'email' => 'admin@drarmankabir.com',
    'password' => 'wrongpassword'
]);
assertTest('Login with wrong password returns error', $badLogin);

// 1.3 Login with missing fields
$missingLogin = test('Missing Fields', 'POST', '/api/auth/login.php', [
    'email' => 'admin@drarmankabir.com'
]);
assertTest('Login with missing fields returns error', $missingLogin);

// 1.4 Verify session
$verify = test('Verify Session', 'GET', '/api/auth/verify.php', null, $token);
assertTest('Verify session with valid token', $verify);

// 1.5 Verify without token
$verifyNoToken = test('Verify No Token', 'GET', '/api/auth/verify.php');
echo "  ℹ️  Verify no-token: HTTP={$verifyNoToken['http']} Msg={$verifyNoToken['message']}\n";

// ─── MULTI-USER LOGIN TESTS ─────────────────────────────────────────────
echo "\n─── Multi-User Auth ──────────────────────────────────────\n";

$users = [
    ['email' => 'dr.arman@drarmankabir.com', 'password' => 'admin123', 'role' => 'consultant_doctor', 'name' => 'Doctor'],
    ['email' => 'nurse@drarmankabir.com', 'password' => 'admin123', 'role' => 'nurse', 'name' => 'Nurse'],
    ['email' => 'reception@drarmankabir.com', 'password' => 'admin123', 'role' => 'reception', 'name' => 'Reception'],
];

foreach ($users as $u) {
    $result = test("{$u['name']} Login", 'POST', '/api/auth/login.php', [
        'email' => $u['email'],
        'password' => $u['password']
    ]);
    assertTest("{$u['name']} login (role: {$u['role']})", $result);
}

// ─── PATIENT TESTS ───────────────────────────────────────────────────────
echo "\n─── Patient Module ───────────────────────────────────────\n";

// 2.1 Create patient
$createPatient = test('Create Patient', 'POST', '/api/patients/create.php', [
    'fullName' => 'Rahim Mia',
    'nameBn' => 'রহিম মিয়া',
    'phone' => '+8801712345678',
    'email' => 'rahim@example.com',
    'gender' => 'male',
    'dateOfBirth' => '1985-06-20',
    'address' => '42, Mohakhali, Dhaka',
    'bloodGroup' => 'B+',
    'weight' => 72.5,
    'height' => 170,
    'patientType' => 'outdoor',
], $token);
assertTest('Create patient with all fields', $createPatient);
$patientId = $createPatient['data']['id'] ?? 0;
$regNumber = $createPatient['data']['register_number'] ?? '';

// 2.2 Create second patient
$createPatient2 = test('Create Patient 2', 'POST', '/api/patients/create.php', [
    'fullName' => 'Fatima Begum',
    'phone' => '+8801712345679',
    'gender' => 'female',
    'dateOfBirth' => '1992-03-10',
    'address' => '25, Gulshan, Dhaka',
    'patientType' => 'outdoor',
], $token);
assertTest('Create second patient', $createPatient2);
$patientId2 = $createPatient2['data']['id'] ?? 0;

// 2.3 List patients
$listPatients = test('List Patients', 'GET', '/api/patients/list.php', ['page' => 1, 'per_page' => 10], $token);
assertTest('List patients with pagination', $listPatients);
echo "       Total: " . ($listPatients['data']['total'] ?? 'N/A') . "\n";

// 2.4 Get single patient
$getPatient = test('Get Patient', 'GET', '/api/patients/get.php', ['id' => $patientId], $token);
assertTest('Get patient by ID', $getPatient);

// 2.5 Search patient
$searchPatient = test('Search Patient', 'GET', '/api/patients/list.php', ['search' => 'Rahim'], $token);
assertTest('Search patients by name', $searchPatient);
echo "       Results: " . (count($searchPatient['data']['data'] ?? []) > 0 ? "Found" : "None") . "\n";

// 2.6 Update patient
$updatePatient = test('Update Patient', 'POST', '/api/patients/update.php', [
    'id' => $patientId,
    'full_name' => 'Rahim Mia Updated',
    'weight' => 73.0,
    'blood_group' => 'B+',
], $token);
assertTest('Update patient fields', $updatePatient);

// 2.7 Verify update persisted
$verifyPatient = test('Verify Update', 'GET', '/api/patients/get.php', ['id' => $patientId], $token);
assertTest('Verify patient update persisted', $verifyPatient);
$nameAfterUpdate = $verifyPatient['data']['full_name'] ?? '';
echo "       Name after update: $nameAfterUpdate\n";

// 2.8 Delete patient
$deletePatient = test('Delete Patient', 'POST', '/api/patients/delete.php', ['id' => $patientId], $token);
assertTest('Delete patient', $deletePatient);

// 2.9 Verify deletion
$verifyDelete = test('Verify Deletion', 'GET', '/api/patients/get.php', ['id' => $patientId], $token);
echo "  ℹ️  Verify deleted: HTTP={$verifyDelete['http']} Msg={$verifyDelete['message']}\n";

// 2.10 Patient filter by type
$filterPatients = test('Filter by Type', 'GET', '/api/patients/list.php', ['patient_type' => 'outdoor', 'page' => 1], $token);
assertTest('Filter patients by type', $filterPatients);

// ─── APPOINTMENT TESTS ──────────────────────────────────────────────────
echo "\n─── Appointment Module ───────────────────────────────────\n";

// 3.1 Create appointment for patient 2
$createAppt = test('Create Appointment', 'POST', '/api/appointments/create.php', [
    'patient_id' => $patientId2,
    'appointment_date' => date('Y-m-d', strtotime('+1 day')),
    'appointment_time' => '10:00:00',
    'type' => 'regular',
    'chief_complaint' => 'Fever and cough for 3 days',
    'status' => 'scheduled',
], $token);
assertTest('Create appointment for patient', $createAppt);
$apptId = $createAppt['data']['id'] ?? 0;
$serialNo = $createAppt['data']['serial_number'] ?? '';

// 3.2 List appointments
$listAppts = test('List Appointments', 'GET', '/api/appointments/list.php', ['page' => 1, 'per_page' => 10], $token);
assertTest('List all appointments', $listAppts);

// 3.3 Update appointment status
$updateAppt = test('Update Appointment Status', 'POST', '/api/appointments/update.php', [
    'id' => $apptId,
    'status' => 'confirmed',
    'notes' => 'Patient confirmed via phone',
], $token);
assertTest('Update appointment status', $updateAppt);

// 3.4 Verify update
$verifyAppt = test('Verify Appointment Update', 'GET', '/api/appointments/list.php', ['status' => 'confirmed'], $token);
assertTest('Verify appointment status changed', $verifyAppt);

// 3.5 Appointment with invalid type
$invalidAppt = test('Invalid Appointment Type', 'POST', '/api/appointments/create.php', [
    'patient_id' => $patientId2,
    'appointment_date' => date('Y-m-d', strtotime('+2 days')),
    'type' => 'invalid_type',
], $token);
echo "  ℹ️  Invalid type: HTTP={$invalidAppt['http']} Msg={$invalidAppt['message']}\n";

// 3.6 Filter appointments by date
$filterAppts = test('Filter by Date', 'GET', '/api/appointments/list.php', [
    'date_from' => date('Y-m-d'),
    'date_to' => date('Y-m-d', strtotime('+7 days')),
], $token);
assertTest('Filter appointments by date range', $filterAppts);

echo "\nPress Ctrl+C to stop, or wait for full results...\n";
echo "──────────────────────────────────────────────────────────\n";
echo "Tests passed: $pass | Tests failed: $fail\n";
echo "──────────────────────────────────────────────────────────\n";

// ─── VISITS / ENCOUNTERS ────────────────────────────────────────────────
echo "\n─── Clinical Module - Visits ──────────────────────────────\n";

// 4.1 Create visit
$createVisit = test('Create Visit', 'POST', '/api/visits/create.php', [
    'patient_id' => $patientId2,
    'visit_type' => 'outpatient',
    'visit_date' => date('Y-m-d'),
    'chief_complaint' => 'Cough and fever',
    'history_of_present_illness' => 'Started 3 days ago with mild fever',
    'diagnosis' => 'Upper respiratory tract infection',
], $token);
assertTest('Create visit for patient', $createVisit);
$visitId = $createVisit['data']['id'] ?? 0;

// 4.2 List visits
$listVisits = test('List Visits', 'GET', '/api/visits/list.php', ['patient_id' => $patientId2], $token);
assertTest('List visits for patient', $listVisits);

echo "\n─── Clinical Module - Prescriptions ───────────────────────\n";

// 5.1 Create prescription
$createRx = test('Create Prescription', 'POST', '/api/prescriptions/create.php', [
    'patient_id' => $patientId2,
    'visit_id' => $visitId,
    'prescription_date' => date('Y-m-d'),
    'diagnosis' => 'URTI',
    'medications' => [
        [
            'name' => 'Paracetamol',
            'dose' => '500mg',
            'frequency' => '3 times daily',
            'duration' => '5 days',
            'instructions' => 'After meals',
            'drug_form' => 'tablet',
            'route' => 'oral',
        ],
        [
            'name' => 'Amoxicillin',
            'dose' => '500mg',
            'frequency' => '2 times daily',
            'duration' => '7 days',
            'drug_form' => 'capsule',
            'route' => 'oral',
        ],
    ],
], $token);
assertTest('Create prescription with medications', $createRx);
$rxId = $createRx['data']['id'] ?? 0;

// 5.2 List prescriptions
$listRx = test('List Prescriptions', 'GET', '/api/prescriptions/list.php', ['patient_id' => $patientId2], $token);
assertTest('List prescriptions for patient', $listRx);

echo "\n─── Clinical Module - Vitals ──────────────────────────────\n";

// 6.1 Record vitals
$createVitals = test('Record Vitals', 'POST', '/api/vitals/record.php', [
    'patient_id' => $patientId2,
    'visit_id' => $visitId,
    'blood_pressure_systolic' => 120,
    'blood_pressure_diastolic' => 80,
    'pulse' => 72,
    'temperature' => 38.5,
    'oxygen_saturation' => 98,
    'weight' => 65.0,
    'height' => 165,
], $token);
assertTest('Record vital signs', $createVitals);

// 6.2 List vitals
$listVitals = test('List Vitals', 'GET', '/api/vitals/list.php', ['patient_id' => $patientId2], $token);
assertTest('List vitals for patient', $listVitals);

echo "\n─── Clinical Module - SOAP Notes ─────────────────────────\n";

// 7.1 Create SOAP note
$createNote = test('Create SOAP Note', 'POST', '/api/clinical/notes-create.php', [
    'patient_id' => $patientId2,
    'visit_id' => $visitId,
    'note_type' => 'soap',
    'subjective' => 'Patient reports fever and cough for 3 days',
    'objective' => 'Temp 38.5C, Pulse 72, BP 120/80, Chest clear',
    'assessment' => 'Upper respiratory tract infection',
    'plan' => 'Paracetamol 500mg TDS x 5 days, Amoxicillin 500mg BD x 7 days',
], $token);
assertTest('Create SOAP clinical note', $createNote);

// 7.2 List notes
$listNotes = test('List Clinical Notes', 'GET', '/api/clinical/notes-list.php', ['patient_id' => $patientId2], $token);
assertTest('List clinical notes for patient', $listNotes);

echo "\n──────────────────────────────────────────────────────────\n";
echo "  Mid-way Summary\n";
echo "  Tests passed: $pass | Tests failed: $fail\n";
echo "──────────────────────────────────────────────────────────\n";

// ─── BILLING TESTS ──────────────────────────────────────────────────────
echo "\n─── Billing Module - Payments ─────────────────────────────\n";

// 8.1 Create payment
$createPayment = test('Create Payment', 'POST', '/api/payments/create.php', [
    'patient_id' => $patientId2,
    'amount' => 1500.00,
    'payment_type' => 'consultation',
    'payment_method' => 'cash',
    'payment_date' => date('Y-m-d'),
    'notes' => 'Consultation fee',
], $token);
assertTest('Create payment record', $createPayment);
$paymentId = $createPayment['data']['id'] ?? 0;

// 8.2 List payments
$listPayments = test('List Payments', 'GET', '/api/payments/list.php', ['patient_id' => $patientId2], $token);
assertTest('List payments for patient', $listPayments);

echo "\n─── Billing Module - Invoices ─────────────────────────────\n";

// 9.1 Create invoice
$createInvoice = test('Create Invoice', 'POST', '/api/invoices/create.php', [
    'patient_id' => $patientId2,
    'invoice_date' => date('Y-m-d'),
    'items' => [
        ['description' => 'Consultation fee', 'quantity' => 1, 'unit_price' => 1000.00, 'item_type' => 'consultation'],
        ['description' => 'CBC Test', 'quantity' => 1, 'unit_price' => 500.00, 'item_type' => 'investigation'],
    ],
    'discount' => 0,
    'tax' => 0,
    'notes' => 'Initial visit',
], $token);
assertTest('Create invoice with items', $createInvoice);
$invoiceId = $createInvoice['data']['id'] ?? 0;
$invoiceNumber = $createInvoice['data']['invoice_number'] ?? '';

// 9.2 List invoices
$listInvoices = test('List Invoices', 'GET', '/api/invoices/list.php', ['patient_id' => $patientId2], $token);
assertTest('List invoices for patient', $listInvoices);

echo "\n─── Investigation Module ──────────────────────────────────\n";

// 10.1 Create investigation
$createInvestigation = test('Create Investigation', 'POST', '/api/investigations/create.php', [
    'patient_id' => $patientId2,
    'visit_id' => $visitId,
    'test_name' => 'CBC',
    'test_category' => 'Hematology',
    'instructions' => 'Fasting not required',
], $token);
assertTest('Order investigation', $createInvestigation);
$invId = $createInvestigation['data']['id'] ?? 0;

// 10.2 List investigations
$listInvestigations = test('List Investigations', 'GET', '/api/investigations/list.php', ['patient_id' => $patientId2], $token);
assertTest('List investigations for patient', $listInvestigations);

echo "\n─── Notification Module ───────────────────────────────────\n";

// 11.1 Create notification
$createNotif = test('Create Notification', 'POST', '/api/notifications/create.php', [
    'user_id' => 1,
    'patient_id' => $patientId2,
    'type' => 'appointment',
    'title' => 'Appointment Reminder',
    'message' => 'Patient has appointment tomorrow at 10:00 AM',
    'severity' => 'info',
], $token);
assertTest('Create notification', $createNotif);

// 11.2 List notifications
$listNotifs = test('List Notifications', 'GET', '/api/notifications/list.php', ['user_id' => 1], $token);
assertTest('List notifications for user', $listNotifs);

echo "\n─── Staff Module ──────────────────────────────────────────\n";

// 12.1 List staff
$listStaff = test('List Staff', 'GET', '/api/staff/list.php', [], $token);
assertTest('List all staff/users', $listStaff);

echo "\n─── Settings Module ───────────────────────────────────────\n";

// 13.1 Get settings
$getSettings = test('Get Settings', 'GET', '/api/settings/get.php', [], $token);
assertTest('Get site settings', $getSettings);

echo "\n─── Health Check ──────────────────────────────────────────\n";

// 14.1 Unauthenticated info
$info = test('Health Check', 'GET', '/api/info.php', []);
echo "  ℹ️  Health check: HTTP={$info['http']}\n";

// ─── AUTH LOGOUT ────────────────────────────────────────────────────────
echo "\n─── Logout ────────────────────────────────────────────────\n";

// 15.1 Logout
$logout = test('Logout', 'POST', '/api/auth/logout.php', [], $token);
assertTest('Logout user', $logout);

// 15.2 Verify token no longer works
$verifyAfterLogout = test('Verify After Logout', 'GET', '/api/auth/verify.php', null, $token);
echo "  ℹ️  Verify after logout: HTTP={$verifyAfterLogout['http']} Msg={$verifyAfterLogout['message']}\n";

// ─── SUMMARY ────────────────────────────────────────────────────────────
echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  FINAL RESULTS\n";
echo "  Total Tests Run: " . ($pass + $fail) . "\n";
echo "  ✅ Passed: $pass\n";
echo "  ❌ Failed: $fail\n";
echo "  Date: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════\n";
