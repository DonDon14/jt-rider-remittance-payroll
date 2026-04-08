<?php

use App\Models\DeliverySubmissionModel;
use App\Models\RemittanceAccountModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class WorkflowSmokeTest extends CIUnitTestCase
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

    public function testRiderDashboardLoadsWithConfiguredRemittanceAccounts(): void
    {
        $result = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'rider',
            'user_id' => 2,
            'rider_id' => 3,
            'force_password_change' => false,
            'username' => 'r-1003',
        ])->get('/rider/3');

        $result->assertOK();
        $result->assertSee('Submit Delivery Record');
        $result->assertSee('Remittance Account Used');
    }

    public function testRiderSubmissionStoresSelectedRemittanceAccount(): void
    {
        $security = service('security');
        $tokenName = $security->getTokenName();
        $tokenHash = $security->generateHash();

        $result = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'rider',
            'user_id' => 2,
            'rider_id' => 3,
            'force_password_change' => false,
            'username' => 'r-1003',
        ])->post('/rider/delivery-submissions', [
            $tokenName => $tokenHash,
            'delivery_date' => '2026-04-07',
            'allocated_parcels' => '20',
            'successful_deliveries' => '18',
            'expected_remittance' => '2500.00',
            'remittance_account_id' => '1',
            'notes' => 'Submitted from workflow smoke test.',
        ]);

        $result->assertRedirect();
        $result->assertRedirectTo(site_url('/rider-dashboard'));

        $submission = (new DeliverySubmissionModel())
            ->where('rider_id', 3)
            ->where('delivery_date', '2026-04-07')
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame(1, (int) $submission['remittance_account_id']);
        $this->assertSame('PENDING', $submission['status']);
    }

    public function testAdminCanCreateRemittanceAccount(): void
    {
        $security = service('security');
        $tokenName = $security->getTokenName();
        $tokenHash = $security->generateHash();

        $result = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'admin',
            'user_id' => 1,
            'force_password_change' => false,
            'username' => 'admin',
        ])->post('/admin/settings/remittance-accounts', [
            $tokenName => $tokenHash,
            'account_name' => 'GCash Clearing Account',
            'account_number' => 'GC-7788',
            'description' => 'Used for rider handoff testing.',
            'sort_order' => '2',
            'is_active' => '1',
        ]);

        $result->assertRedirect();
        $result->assertRedirectTo(site_url('/admin/settings'));

        $account = (new RemittanceAccountModel())
            ->where('account_name', 'GCash Clearing Account')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('GC-7788', $account['account_number']);
        $this->assertTrue((bool) $account['is_active']);
    }

    public function testAdminApprovalCreatesDeliveryRecordFromSubmission(): void
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->table('delivery_submissions')->insert([
            'id' => 1,
            'rider_id' => 3,
            'delivery_date' => '2026-04-08',
            'allocated_parcels' => 24,
            'successful_deliveries' => 21,
            'failed_deliveries' => 3,
            'expected_remittance' => 3200.00,
            'remittance_account_id' => 1,
            'notes' => 'Pending approval from smoke test.',
            'status' => 'PENDING',
            'processed_delivery_record_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $security = service('security');
        $tokenName = $security->getTokenName();
        $tokenHash = $security->generateHash();

        $result = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'admin',
            'user_id' => 1,
            'force_password_change' => false,
            'username' => 'admin',
        ])->post('/admin/delivery-submissions/1/approve', [
            $tokenName => $tokenHash,
            'commission_rate' => '13.25',
        ]);

        $result->assertRedirect();
        $result->assertRedirectTo(site_url('/admin/remittance/1'));

        $delivery = $db->table('delivery_records')->where('id', 1)->get()->getRowArray();
        $submission = $db->table('delivery_submissions')->where('id', 1)->get()->getRowArray();
        $auditLogs = $db->table('delivery_audit_logs')->where('delivery_submission_id', 1)->countAllResults();

        $this->assertNotNull($delivery);
        $this->assertSame('RIDER_SUBMISSION', $delivery['entry_source']);
        $this->assertSame(1, (int) $delivery['remittance_account_id']);
        $this->assertSame(278.25, (float) $delivery['total_due']);
        $this->assertSame('APPROVED', $submission['status']);
        $this->assertSame(1, (int) $submission['processed_delivery_record_id']);
        $this->assertSame(2, $auditLogs);
    }

    public function testAdminSaveRemittancePersistsVarianceAndAccount(): void
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->table('delivery_records')->insert([
            'id' => 1,
            'rider_id' => 3,
            'delivery_date' => '2026-04-09',
            'allocated_parcels' => 25,
            'successful_deliveries' => 20,
            'failed_deliveries' => 5,
            'total_due' => 265.00,
            'expected_remittance' => 2500.00,
            'remittance_account_id' => 1,
            'commission_rate' => 13.25,
            'notes' => 'Ready for remittance.',
            'entry_source' => 'RIDER_SUBMISSION',
            'source_submission_id' => null,
            'created_by_user_id' => 1,
            'last_admin_reason' => 'Seeded for remittance test.',
            'payroll_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $security = service('security');
        $tokenName = $security->getTokenName();
        $tokenHash = $security->generateHash();

        $result = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'admin',
            'user_id' => 1,
            'force_password_change' => false,
            'username' => 'admin',
        ])->post('/admin/remittance/1', [
            $tokenName => $tokenHash,
            'denom_1000' => '2',
            'denom_100' => '4',
            'denom_50' => '1',
            'cash_remitted' => '2450.00',
        ]);

        $result->assertRedirect();
        $result->assertRedirectTo(site_url('/admin/remittance/1'));

        $remittance = $db->table('remittances')->where('delivery_record_id', 1)->get()->getRowArray();

        $this->assertNotNull($remittance);
        $this->assertSame(1, (int) $remittance['remittance_account_id']);
        $this->assertSame(2450.0, (float) $remittance['total_remitted']);
        $this->assertSame(50.0, (float) $remittance['variance_amount']);
        $this->assertSame('SHORT', $remittance['variance_type']);
    }


    public function testDenominationCashOverridesStaleZeroFieldAndAddsGcashToGrandTotal(): void
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->table('delivery_records')->insert([
            'id' => 2,
            'rider_id' => 3,
            'delivery_date' => '2026-04-11',
            'allocated_parcels' => 20,
            'successful_deliveries' => 18,
            'failed_deliveries' => 2,
            'total_due' => 238.50,
            'expected_remittance' => 2500.00,
            'remittance_account_id' => 1,
            'commission_rate' => 13.25,
            'notes' => 'Mixed remittance test.',
            'entry_source' => 'RIDER_SUBMISSION',
            'source_submission_id' => null,
            'created_by_user_id' => 1,
            'last_admin_reason' => 'Seeded for mixed remittance test.',
            'payroll_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $security = service('security');
        $tokenName = $security->getTokenName();
        $tokenHash = $security->generateHash();

        $result = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'admin',
            'user_id' => 1,
            'force_password_change' => false,
            'username' => 'admin',
        ])->post('/admin/remittance/2', [
            $tokenName => $tokenHash,
            'denom_500' => '2',
            'cash_remitted' => '0',
            'gcash_remitted' => '1500.00',
            'gcash_reference' => 'GC-TEST-001',
        ]);

        $result->assertRedirect();
        $result->assertRedirectTo(site_url('/admin/remittance/2'));

        $remittance = $db->table('remittances')->where('delivery_record_id', 2)->get()->getRowArray();

        $this->assertNotNull($remittance);
        $this->assertSame(1000.0, (float) $remittance['cash_remitted']);
        $this->assertSame(1500.0, (float) $remittance['gcash_remitted']);
        $this->assertSame(2500.0, (float) $remittance['total_remitted']);
        $this->assertSame('BALANCED', $remittance['variance_type']);
    }
    public function testAdminGeneratePayrollLocksCoveredDeliveries(): void
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->table('delivery_records')->insert([
            'id' => 1,
            'rider_id' => 3,
            'delivery_date' => '2026-04-10',
            'allocated_parcels' => 18,
            'successful_deliveries' => 16,
            'failed_deliveries' => 2,
            'total_due' => 212.00,
            'expected_remittance' => 2100.00,
            'remittance_account_id' => 1,
            'commission_rate' => 13.25,
            'notes' => 'Seeded for payroll test.',
            'entry_source' => 'ADMIN_MANUAL',
            'source_submission_id' => null,
            'created_by_user_id' => 1,
            'last_admin_reason' => 'Seeded for payroll test.',
            'payroll_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('remittances')->insert([
            'id' => 1,
            'rider_id' => 3,
            'delivery_record_id' => 1,
            'delivery_date' => '2026-04-10',
            'remittance_account_id' => 1,
            'denom_025' => 0,
            'denom_1' => 0,
            'denom_5' => 0,
            'denom_10' => 0,
            'denom_20' => 0,
            'denom_50' => 0,
            'denom_100' => 1,
            'denom_500' => 0,
            'denom_1000' => 2,
            'total_due' => 212.00,
            'total_remitted' => 2100.00,
            'supposed_remittance' => 2100.00,
            'actual_remitted' => 2100.00,
            'variance_amount' => 0.00,
            'variance_type' => 'BALANCED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $security = service('security');
        $tokenName = $security->getTokenName();
        $tokenHash = $security->generateHash();

        $result = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'admin',
            'user_id' => 1,
            'force_password_change' => false,
            'username' => 'admin',
        ])->post('/admin/payroll/generate', [
            $tokenName => $tokenHash,
            'rider_id' => '3',
            'payroll_month' => '2026-04',
            'cutoff_period' => 'FIRST',
        ]);

        $result->assertRedirect();
        $result->assertRedirectTo(site_url('/admin/payroll'));

        $payroll = $db->table('payrolls')->where('rider_id', 3)->get()->getRowArray();
        $delivery = $db->table('delivery_records')->where('id', 1)->get()->getRowArray();

        $this->assertNotNull($payroll);
        $this->assertSame('2026-04-01', $payroll['start_date']);
        $this->assertSame('2026-04-15', $payroll['end_date']);
        $this->assertSame(16, (int) $payroll['total_successful']);
        $this->assertSame(212.0, (float) $payroll['gross_earnings']);
        $this->assertSame(212.0, (float) $payroll['net_pay']);
        $this->assertSame('GENERATED', $payroll['payroll_status']);
        $this->assertSame((int) $payroll['id'], (int) $delivery['payroll_id']);
    }

    public function testPayrollReleaseAndRiderConfirmationUpdatePayoutStatus(): void
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

        $security = service('security');
        $tokenName = $security->getTokenName();
        $tokenHash = $security->generateHash();

        $releaseResult = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'admin',
            'user_id' => 1,
            'force_password_change' => false,
            'username' => 'admin',
        ])->post('/admin/payroll/1/release', [
            $tokenName => $tokenHash,
            'payout_method' => 'BANK_TRANSFER',
            'payout_reference' => 'TRX-20260415',
        ]);

        $releaseResult->assertRedirect();
        $releaseResult->assertRedirectTo(site_url('/admin/payroll'));

        $releasedPayroll = $db->table('payrolls')->where('id', 1)->get()->getRowArray();

        $this->assertNotNull($releasedPayroll);
        $this->assertSame('RELEASED', $releasedPayroll['payroll_status']);
        $this->assertSame('BANK_TRANSFER', $releasedPayroll['payout_method']);
        $this->assertSame('TRX-20260415', $releasedPayroll['payout_reference']);
        $this->assertNotEmpty($releasedPayroll['released_at']);
        $this->assertSame(1, (int) $releasedPayroll['released_by_user_id']);

        $tokenHash = $security->generateHash();
        $confirmResult = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'rider',
            'user_id' => 2,
            'rider_id' => 3,
            'force_password_change' => false,
            'username' => 'r-1003',
        ])->post('/rider/payroll/1/confirm', [
            $tokenName => $tokenHash,
            'received_notes' => 'Salary received in full.',
        ]);

        $confirmResult->assertRedirect();
        $confirmResult->assertRedirectTo(site_url('/rider-dashboard'));

        $confirmedPayroll = $db->table('payrolls')->where('id', 1)->get()->getRowArray();

        $this->assertSame('RECEIVED', $confirmedPayroll['payroll_status']);
        $this->assertNotEmpty($confirmedPayroll['received_at']);
        $this->assertSame('Salary received in full.', $confirmedPayroll['received_notes']);
    }

    public function testAdminResetRiderPasswordGeneratesNonPredictableTemporaryPassword(): void
    {
        $db = db_connect();
        $security = service('security');
        $tokenName = $security->getTokenName();
        $tokenHash = $security->generateHash();

        $result = $this->withSession([
            'isLoggedIn' => true,
            'role' => 'admin',
            'user_id' => 1,
            'force_password_change' => false,
            'username' => 'admin',
        ])->post('/admin/riders/3/reset-password', [
            $tokenName => $tokenHash,
        ]);

        $result->assertRedirect();
        $result->assertRedirectTo(site_url('/admin/riders'));

        $user = $db->table('users')->where('id', 2)->get()->getRowArray();

        $this->assertNotNull($user);
        $this->assertSame('r-1003', $user['username']);
        $this->assertTrue((bool) $user['force_password_change']);
        $this->assertFalse(password_verify('r-1003123', $user['password_hash']));
        $this->assertFalse(password_verify('secret123', $user['password_hash']));
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
            'payroll_adjustments',
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
            cash_remitted REAL NULL,
            gcash_remitted REAL NULL,
            gcash_reference VARCHAR(100) NULL,
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
            cash_remitted REAL NULL,
            gcash_remitted REAL NULL,
            gcash_reference VARCHAR(100) NULL,
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

        $db->query('CREATE TABLE ' . $prefix . 'payroll_adjustments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rider_id INTEGER NOT NULL,
            adjustment_date DATE NOT NULL,
            type VARCHAR(20) NOT NULL,
            amount REAL NOT NULL DEFAULT 0,
            description VARCHAR(255) NULL,
            batch_reference VARCHAR(100) NULL,
            payroll_id INTEGER NULL,
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
            cash_remitted REAL NULL,
            gcash_remitted REAL NULL,
            gcash_reference VARCHAR(100) NULL,
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














