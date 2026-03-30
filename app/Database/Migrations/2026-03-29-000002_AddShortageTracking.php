<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShortageTracking extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('supposed_remittance', 'remittances')) {
            $this->forge->addColumn('remittances', [
                'supposed_remittance' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'null' => true,
                    'after' => 'total_remitted',
                ],
            ]);
        }

        if (! $this->db->fieldExists('actual_remitted', 'remittances')) {
            $this->forge->addColumn('remittances', [
                'actual_remitted' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                    'null' => true,
                    'after' => 'supposed_remittance',
                ],
            ]);
        }

        if (! $this->db->tableExists('shortage_payments')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'remittance_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'rider_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'payment_date' => [
                    'type' => 'DATE',
                ],
                'amount' => [
                    'type' => 'DECIMAL',
                    'constraint' => '12,2',
                ],
                'notes' => [
                    'type' => 'TEXT',
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
            $this->forge->addKey(['remittance_id', 'payment_date']);
            $this->forge->addForeignKey('remittance_id', 'remittances', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('rider_id', 'riders', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('shortage_payments');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('shortage_payments')) {
            $this->forge->dropTable('shortage_payments', true);
        }

        if ($this->db->fieldExists('actual_remitted', 'remittances')) {
            $this->forge->dropColumn('remittances', 'actual_remitted');
        }

        if ($this->db->fieldExists('supposed_remittance', 'remittances')) {
            $this->forge->dropColumn('remittances', 'supposed_remittance');
        }
    }
}
