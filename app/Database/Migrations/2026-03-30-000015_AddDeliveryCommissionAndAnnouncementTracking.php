<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeliveryCommissionAndAnnouncementTracking extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('commission_rate', 'delivery_records')) {
            $this->forge->addColumn('delivery_records', [
                'commission_rate' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 13.00,
                    'after' => 'expected_remittance',
                ],
            ]);
        }

        if ($this->db->fieldExists('commission_rate', 'delivery_records')) {
            $rows = $this->db->table('delivery_records')
                ->select('id, successful_deliveries, total_due')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $successful = max(0, (int) ($row['successful_deliveries'] ?? 0));
                $commissionRate = $successful > 0
                    ? round((float) ($row['total_due'] ?? 0) / $successful, 2)
                    : 0.0;

                $this->db->table('delivery_records')
                    ->where('id', (int) $row['id'])
                    ->update(['commission_rate' => $commissionRate]);
            }
        }

        if (! $this->db->fieldExists('expires_at', 'announcements')) {
            $this->forge->addColumn('announcements', [
                'expires_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'published_at',
                ],
            ]);
        }

        if (! $this->db->fieldExists('last_seen_announcement_id', 'users')) {
            $this->forge->addColumn('users', [
                'last_seen_announcement_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'is_active',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('commission_rate', 'delivery_records')) {
            $this->forge->dropColumn('delivery_records', 'commission_rate');
        }

        if ($this->db->fieldExists('expires_at', 'announcements')) {
            $this->forge->dropColumn('announcements', 'expires_at');
        }

        if ($this->db->fieldExists('last_seen_announcement_id', 'users')) {
            $this->forge->dropColumn('users', 'last_seen_announcement_id');
        }
    }
}
