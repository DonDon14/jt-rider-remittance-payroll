<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRemittanceAccountsAndLinks extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('remittance_accounts')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'account_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                ],
                'account_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 80,
                    'null' => true,
                ],
                'description' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'sort_order' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
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
            $this->forge->createTable('remittance_accounts');

            $this->db->table('remittance_accounts')->insert([
                'account_name' => 'Main J&T Account',
                'account_number' => null,
                'description' => 'Default account for rider remittance selection.',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($this->db->tableExists('delivery_submissions') && ! $this->db->fieldExists('remittance_account_id', 'delivery_submissions')) {
            $this->forge->addColumn('delivery_submissions', [
                'remittance_account_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'expected_remittance',
                ],
            ]);
        }

        if ($this->db->tableExists('delivery_records') && ! $this->db->fieldExists('remittance_account_id', 'delivery_records')) {
            $this->forge->addColumn('delivery_records', [
                'remittance_account_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'expected_remittance',
                ],
            ]);
        }

        if ($this->db->tableExists('remittances') && ! $this->db->fieldExists('remittance_account_id', 'remittances')) {
            $this->forge->addColumn('remittances', [
                'remittance_account_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'delivery_date',
                ],
            ]);
        }
    }

    public function down()
    {
        foreach (['delivery_submissions', 'delivery_records', 'remittances'] as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('remittance_account_id', $table)) {
                $this->forge->dropColumn($table, 'remittance_account_id');
            }
        }

        if ($this->db->tableExists('remittance_accounts')) {
            $this->forge->dropTable('remittance_accounts', true);
        }
    }
}
