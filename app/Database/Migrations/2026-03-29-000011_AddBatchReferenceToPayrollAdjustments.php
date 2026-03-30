<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBatchReferenceToPayrollAdjustments extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('batch_reference', 'payroll_adjustments')) {
            $this->forge->addColumn('payroll_adjustments', [
                'batch_reference' => [
                    'type' => 'VARCHAR',
                    'constraint' => 60,
                    'null' => true,
                    'after' => 'description',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('batch_reference', 'payroll_adjustments')) {
            $this->forge->dropColumn('payroll_adjustments', 'batch_reference');
        }
    }
}
