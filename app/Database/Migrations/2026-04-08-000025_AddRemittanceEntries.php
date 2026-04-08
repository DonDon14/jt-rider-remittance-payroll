<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRemittanceEntries extends Migration
{
    public function up()
    {
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
            'remittance_account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'entry_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'INITIAL',
            ],
            'entry_sequence' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 1,
            ],
            'cash_remitted' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'gcash_remitted' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'gcash_reference' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'denom_025' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_1' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_5' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_10' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_20' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_50' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_100' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_500' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_1000' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'total_remitted' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by_user_id' => [
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
        $this->forge->addKey('remittance_id');
        $this->forge->addKey('remittance_account_id');
        $this->forge->addForeignKey('remittance_id', 'remittances', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('remittance_account_id', 'remittance_accounts', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('remittance_entries');
    }

    public function down()
    {
        $this->forge->dropTable('remittance_entries', true);
    }
}
