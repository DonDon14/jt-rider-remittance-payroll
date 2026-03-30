<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPayrollAdjustments extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('payroll_adjustments')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'rider_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'adjustment_date' => [
                    'type' => 'DATE',
                ],
                'type' => [
                    'type' => 'ENUM',
                    'constraint' => ['BONUS', 'DEDUCTION'],
                ],
                'amount' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                ],
                'description' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'payroll_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['rider_id', 'adjustment_date']);
            $this->forge->addForeignKey('rider_id', 'riders', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('payroll_id', 'payrolls', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('payroll_adjustments');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('payroll_adjustments')) {
            $this->forge->dropTable('payroll_adjustments', true);
        }
    }
}
