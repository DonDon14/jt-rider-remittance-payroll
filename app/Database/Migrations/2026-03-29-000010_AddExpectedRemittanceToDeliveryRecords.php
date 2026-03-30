<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExpectedRemittanceToDeliveryRecords extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('expected_remittance', 'delivery_records')) {
            $this->forge->addColumn('delivery_records', [
                'expected_remittance' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'default' => 0,
                    'after' => 'failed_deliveries',
                ],
            ]);
        }

        $this->db->query(
            'UPDATE delivery_records dr
             LEFT JOIN remittances r ON r.delivery_record_id = dr.id
             SET dr.expected_remittance = COALESCE(r.supposed_remittance, dr.expected_remittance, 0)'
        );
    }

    public function down()
    {
        if ($this->db->fieldExists('expected_remittance', 'delivery_records')) {
            $this->forge->dropColumn('delivery_records', 'expected_remittance');
        }
    }
}
