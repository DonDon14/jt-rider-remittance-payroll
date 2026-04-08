<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDigitalRemittanceFields extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('remittances')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('cash_remitted', 'remittances')) {
            $fields['cash_remitted'] = [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'null' => true,
                'after' => 'remittance_account_id',
            ];
        }

        if (! $this->db->fieldExists('gcash_remitted', 'remittances')) {
            $fields['gcash_remitted'] = [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'null' => true,
                'after' => 'cash_remitted',
            ];
        }

        if (! $this->db->fieldExists('gcash_reference', 'remittances')) {
            $fields['gcash_reference'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'gcash_remitted',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('remittances', $fields);
        }

        if ($this->db->fieldExists('cash_remitted', 'remittances')) {
            $this->db->query('UPDATE ' . $this->db->prefixTable('remittances') . ' SET cash_remitted = total_remitted WHERE cash_remitted IS NULL');
        }

        if ($this->db->fieldExists('gcash_remitted', 'remittances')) {
            $this->db->query('UPDATE ' . $this->db->prefixTable('remittances') . ' SET gcash_remitted = 0 WHERE gcash_remitted IS NULL');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('remittances')) {
            return;
        }

        $dropFields = [];
        foreach (['cash_remitted', 'gcash_remitted', 'gcash_reference'] as $field) {
            if ($this->db->fieldExists($field, 'remittances')) {
                $dropFields[] = $field;
            }
        }

        if ($dropFields !== []) {
            $this->forge->dropColumn('remittances', $dropFields);
        }
    }
}
