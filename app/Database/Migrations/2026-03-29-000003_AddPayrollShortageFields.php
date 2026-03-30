<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPayrollShortageFields extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('shortage_deductions', 'payrolls')) {
            $this->forge->addColumn('payrolls', [
                'shortage_deductions' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'default' => 0,
                    'after' => 'remittance_variance',
                ],
            ]);
        }

        if (! $this->db->fieldExists('shortage_payments_received', 'payrolls')) {
            $this->forge->addColumn('payrolls', [
                'shortage_payments_received' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'default' => 0,
                    'after' => 'shortage_deductions',
                ],
            ]);
        }

        if (! $this->db->fieldExists('outstanding_shortage_balance', 'payrolls')) {
            $this->forge->addColumn('payrolls', [
                'outstanding_shortage_balance' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'default' => 0,
                    'after' => 'shortage_payments_received',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('outstanding_shortage_balance', 'payrolls')) {
            $this->forge->dropColumn('payrolls', 'outstanding_shortage_balance');
        }

        if ($this->db->fieldExists('shortage_payments_received', 'payrolls')) {
            $this->forge->dropColumn('payrolls', 'shortage_payments_received');
        }

        if ($this->db->fieldExists('shortage_deductions', 'payrolls')) {
            $this->forge->dropColumn('payrolls', 'shortage_deductions');
        }
    }
}
