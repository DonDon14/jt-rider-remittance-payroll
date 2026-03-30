<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        $riders = [
            [
                'rider_code' => 'R-1001',
                'name' => 'John Dela Cruz',
                'contact_number' => '09171234567',
                'commission_rate' => 13.00,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'rider_code' => 'R-1002',
                'name' => 'Maria Santos',
                'contact_number' => '09179876543',
                'commission_rate' => 13.00,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('riders')->insertBatch($riders);
    }
}
