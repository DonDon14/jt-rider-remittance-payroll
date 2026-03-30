<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPayrollRangeAndLocks extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('start_date', 'payrolls')) {
            $this->forge->addColumn('payrolls', [
                'start_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'after' => 'month_year',
                ],
            ]);
        }

        if (! $this->db->fieldExists('end_date', 'payrolls')) {
            $this->forge->addColumn('payrolls', [
                'end_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'after' => 'start_date',
                ],
            ]);
        }

        if (! $this->db->fieldExists('payroll_id', 'delivery_records')) {
            $this->forge->addColumn('delivery_records', [
                'payroll_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'notes',
                ],
            ]);
        }

        if (! $this->db->fieldExists('payroll_id', 'shortage_payments')) {
            $this->forge->addColumn('shortage_payments', [
                'payroll_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'notes',
                ],
            ]);
        }

        try {
            $this->db->query('ALTER TABLE delivery_records ADD CONSTRAINT delivery_records_payroll_fk FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Throwable $e) {
        }

        try {
            $this->db->query('ALTER TABLE shortage_payments ADD CONSTRAINT shortage_payments_payroll_fk FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        try {
            $this->db->query('ALTER TABLE shortage_payments DROP FOREIGN KEY shortage_payments_payroll_fk');
        } catch (\Throwable $e) {
        }

        try {
            $this->db->query('ALTER TABLE delivery_records DROP FOREIGN KEY delivery_records_payroll_fk');
        } catch (\Throwable $e) {
        }

        if ($this->db->fieldExists('payroll_id', 'shortage_payments')) {
            $this->forge->dropColumn('shortage_payments', 'payroll_id');
        }

        if ($this->db->fieldExists('payroll_id', 'delivery_records')) {
            $this->forge->dropColumn('delivery_records', 'payroll_id');
        }

        if ($this->db->fieldExists('end_date', 'payrolls')) {
            $this->forge->dropColumn('payrolls', 'end_date');
        }

        if ($this->db->fieldExists('start_date', 'payrolls')) {
            $this->forge->dropColumn('payrolls', 'start_date');
        }
    }
}
