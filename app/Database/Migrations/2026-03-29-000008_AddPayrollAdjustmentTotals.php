<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPayrollAdjustmentTotals extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('bonus_total', 'payrolls')) {
            $this->forge->addColumn('payrolls', [
                'bonus_total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'default' => 0,
                    'after' => 'shortage_payments_received',
                ],
            ]);
        }

        if (! $this->db->fieldExists('deduction_total', 'payrolls')) {
            $this->forge->addColumn('payrolls', [
                'deduction_total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'default' => 0,
                    'after' => 'bonus_total',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('deduction_total', 'payrolls')) {
            $this->forge->dropColumn('payrolls', 'deduction_total');
        }

        if ($this->db->fieldExists('bonus_total', 'payrolls')) {
            $this->forge->dropColumn('payrolls', 'bonus_total');
        }
    }
}
