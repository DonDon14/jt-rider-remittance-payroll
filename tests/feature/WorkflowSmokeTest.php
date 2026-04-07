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

    private function resetSchema(): void
    {
        $db = db_connect();
        $prefix = $db->getPrefix();

        foreach ([
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








