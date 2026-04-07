<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class ApiSmokeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $this->resetSchema();
        $this->createSchema();
        $this->seedBaseData();
    }

    public function testRiderApiCanLoginAndCreateSubmission(): void
    {
        $loginResult = $this->post('/api/login', [
            'username' => 'r-1003',
            'password' => 'secret123',
            'device_name' => 'android',
        ]);

        $loginResult->assertOK();
        $payload = json_decode((string) $loginResult->getJSON(), true);
        $token = (string) ($payload['data']['token'] ?? '');
        $this->assertNotSame('', $token);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/rider/delivery-submissions', [
            'delivery_date' => '2026-04-12',
            'allocated_parcels' => '22',
            'successful_deliveries' => '19',
            'expected_remittance' => '2800.00',
            'remittance_account_id' => '1',
            'notes' => 'Submitted from API smoke test.',
        ]);

        $result->assertStatus(201);
        $response = json_decode((string) $result->getJSON(), true);
        $this->assertSame('Delivery request submitted.', $response['data']['message'] ?? null);

        $submission = db_connect()->table('delivery_submissions')
            ->where('rider_id', 3)
            ->where('delivery_date', '2026-04-12')
            ->get()
            ->getRowArray();

        $this->assertNotNull($submission);
        $this->assertSame('PENDING', $submission['status']);
    }

    public function testAdminApiCanApproveSubmission(): void
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->table('delivery_submissions')->insert([
            'id' => 1,
            'rider_id' => 3,
            'delivery_date' => '2026-04-13',
            'allocated_parcels' => 24,
            'successful_deliveries' => 20,
            'failed_deliveries' => 4,
            'expected_remittance' => 3000.00,
            'remittance_account_id' => 1,
            'notes' => 'Pending API admin approval.',
            'status' => 'PENDING',
            'processed_delivery_record_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $loginResult = $this->post('/api/login', [
            'username' => 'admin',
            'password' => 'secret123',
            'device_name' => 'admin-phone',
        ]);
        $payload = json_decode((string) $loginResult->getJSON(), true);
        $token = (string) ($payload['data']['token'] ?? '');

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/admin/pending-submissions/1/approve', [
            'commission_rate' => '13.25',
        ]);

        $result->assertOK();
        $response = json_decode((string) $result->getJSON(), true);
        $this->assertSame('Submission approved.', $response['data']['message'] ?? null);

        $delivery = $db->table('delivery_records')->where('source_submission_id', 1)->get()->getRowArray();
        $submission = $db->table('delivery_submissions')->where('id', 1)->get()->getRowArray();

        $this->assertNotNull($delivery);
        $this->assertSame('APPROVED', $submission['status']);
        $this->assertSame(265.0, (float) $delivery['total_due']);
    }

    public function testAdminApiCanReleasePayrollAndRiderCanConfirm(): void
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->table('payrolls')->insert([
            'id' => 1,
            'rider_id' => 3,
            'month_year' => '2026-04-01',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-15',
            'total_successful' => 16,
            'gross_earnings' => 212.00,
            'total_due' => 212.00,
            'total_remitted' => 2100.00,
            'remittance_variance' => 0.00,
            'shortage_deductions' => 0.00,
            'shortage_payments_received' => 0.00,
            'bonus_total' => 0.00,
            'deduction_total' => 0.00,
            'outstanding_shortage_balance' => 0.00,
            'net_pay' => 212.00,
            'payroll_status' => 'GENERATED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $adminLogin = $this->post('/api/login', [
            'username' => 'admin',
            'password' => 'secret123',
            'device_name' => 'admin-phone',
        ]);
        $adminPayload = json_decode((string) $adminLogin->getJSON(), true);
        $adminToken = (string) ($adminPayload['data']['token'] ?? '');

        $releaseResult = $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->post('/api/admin/payrolls/1/release', [
            'payout_method' => 'CASH',
            'payout_reference' => 'CASH-001',
        ]);

        $releaseResult->assertOK();

        $riderLogin = $this->post('/api/login', [
            'username' => 'r-1003',
            'password' => 'secret123',
            'device_name' => 'android',
        ]);
        $riderPayload = json_decode((string) $riderLogin->getJSON(), true);
        $riderToken = (string) ($riderPayload['data']['token'] ?? '');

        $confirmResult = $this->withHeaders([
            'Authorization' => 'Bearer ' . $riderToken,
        ])->post('/api/rider/payroll/1/confirm', [
            'received_notes' => 'Received from API flow.',
        ]);

        $confirmResult->assertOK();

        $payroll = $db->table('payrolls')->where('id', 1)->get()->getRowArray();
        $this->assertSame('RECEIVED', $payroll['payroll_status']);
        $this->assertSame('Received from API flow.', $payroll['received_notes']);
    }

    private function resetSchema(): void
    {
        $db = db_connect();
        $prefix = $db->getPrefix();

        foreach ([
            'api_tokens',
            'delivery_audit_logs',
            'delivery_submissions',
            'shortage_payments',
            'remittances',
            'delivery_records',
            'payrolls',
            'announcements',
            'users',
            'remittance_accounts',
            'riders',
        ] as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $prefix . $table);
        }
    }

    private function createSchema(): void
    {
        $db = db_connect();
        $prefix = $db->getPrefix();

        $db->query('CREATE TABLE ' . $prefix . 'riders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rider_code VARCHAR(40) NOT NULL,
            name VARCHAR(120) NOT NULL,
            contact_number VARCHAR(30) NULL,
            commission_rate REAL NOT NULL DEFAULT 13.00,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'remittance_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_name VARCHAR(120) NOT NULL,
            account_number VARCHAR(80) NULL,
            description VARCHAR(255) NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT "rider",
            rider_id INTEGER NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            force_password_change INTEGER NOT NULL DEFAULT 0,
            last_seen_announcement_id INTEGER NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'delivery_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rider_id INTEGER NOT NULL,
            delivery_date DATE NOT NULL,
            allocated_parcels INTEGER NOT NULL DEFAULT 0,
            successful_deliveries INTEGER NOT NULL DEFAULT 0,
            failed_deliveries INTEGER NOT NULL DEFAULT 0,
            total_due REAL NOT NULL DEFAULT 0,
            expected_remittance REAL NOT NULL DEFAULT 0,
            remittance_account_id INTEGER NULL,
            commission_rate REAL NOT NULL DEFAULT 13.00,
            notes TEXT NULL,
            entry_source VARCHAR(30) NOT NULL DEFAULT "ADMIN_MANUAL",
            source_submission_id INTEGER NULL,
            created_by_user_id INTEGER NULL,
            last_admin_reason VARCHAR(255) NULL,
            payroll_id INTEGER NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'remittances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rider_id INTEGER NOT NULL,
            delivery_record_id INTEGER NOT NULL,
            delivery_date DATE NOT NULL,
            remittance_account_id INTEGER NULL,
            denom_025 INTEGER NOT NULL DEFAULT 0,
            denom_1 INTEGER NOT NULL DEFAULT 0,
            denom_5 INTEGER NOT NULL DEFAULT 0,
            denom_10 INTEGER NOT NULL DEFAULT 0,
            denom_20 INTEGER NOT NULL DEFAULT 0,
            denom_50 INTEGER NOT NULL DEFAULT 0,
            denom_100 INTEGER NOT NULL DEFAULT 0,
            denom_500 INTEGER NOT NULL DEFAULT 0,
            denom_1000 INTEGER NOT NULL DEFAULT 0,
            total_due REAL NOT NULL DEFAULT 0,
            total_remitted REAL NOT NULL DEFAULT 0,
            supposed_remittance REAL NULL,
            actual_remitted REAL NULL,
            variance_amount REAL NOT NULL DEFAULT 0,
            variance_type VARCHAR(20) NOT NULL DEFAULT "BALANCED",
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'shortage_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            remittance_id INTEGER NOT NULL,
            rider_id INTEGER NOT NULL,
            payment_date DATE NOT NULL,
            amount REAL NOT NULL,
            payroll_id INTEGER NULL,
            notes TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'payrolls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rider_id INTEGER NOT NULL,
            month_year DATE NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            total_successful INTEGER NOT NULL DEFAULT 0,
            gross_earnings REAL NOT NULL DEFAULT 0,
            total_due REAL NOT NULL DEFAULT 0,
            total_remitted REAL NOT NULL DEFAULT 0,
            remittance_variance REAL NOT NULL DEFAULT 0,
            shortage_deductions REAL NOT NULL DEFAULT 0,
            shortage_payments_received REAL NOT NULL DEFAULT 0,
            bonus_total REAL NOT NULL DEFAULT 0,
            deduction_total REAL NOT NULL DEFAULT 0,
            outstanding_shortage_balance REAL NOT NULL DEFAULT 0,
            net_pay REAL NOT NULL DEFAULT 0,
            payroll_status VARCHAR(20) NOT NULL DEFAULT "GENERATED",
            payout_method VARCHAR(30) NULL,
            payout_reference VARCHAR(100) NULL,
            released_at DATETIME NULL,
            released_by_user_id INTEGER NULL,
            received_at DATETIME NULL,
            received_notes TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            published_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'delivery_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rider_id INTEGER NOT NULL,
            delivery_date DATE NOT NULL,
            allocated_parcels INTEGER NOT NULL DEFAULT 0,
            successful_deliveries INTEGER NOT NULL DEFAULT 0,
            failed_deliveries INTEGER NOT NULL DEFAULT 0,
            expected_remittance REAL NOT NULL DEFAULT 0,
            remittance_account_id INTEGER NULL,
            notes TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "PENDING",
            processed_delivery_record_id INTEGER NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'api_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            token_name VARCHAR(50) NOT NULL DEFAULT "mobile",
            expires_at DATETIME NULL,
            last_used_at DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $db->query('CREATE TABLE ' . $prefix . 'delivery_audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            delivery_record_id INTEGER NULL,
            delivery_submission_id INTEGER NULL,
            rider_id INTEGER NULL,
            actor_user_id INTEGER NULL,
            actor_role VARCHAR(30) NULL,
            action VARCHAR(60) NOT NULL,
            notes TEXT NULL,
            details_json TEXT NULL,
            created_at DATETIME NULL
        )');
    }

    private function seedBaseData(): void
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->table('riders')->insert([
            'id' => 3,
            'rider_code' => 'R-1003',
            'name' => 'Test Rider',
            'contact_number' => '09170000000',
            'commission_rate' => 13.25,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('remittance_accounts')->insert([
            'id' => 1,
            'account_name' => 'Main J&T Account',
            'account_number' => 'JT-001',
            'description' => 'Primary remittance account',
            'sort_order' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('users')->insertBatch([
            [
                'id' => 1,
                'username' => 'admin',
                'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'rider_id' => null,
                'is_active' => 1,
                'force_password_change' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'username' => 'r-1003',
                'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
                'role' => 'rider',
                'rider_id' => 3,
                'is_active' => 1,
                'force_password_change' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
