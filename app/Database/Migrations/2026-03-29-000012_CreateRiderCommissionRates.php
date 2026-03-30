<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRiderCommissionRates extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('rider_commission_rates')) {
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
                'commission_rate' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                ],
                'effective_date' => [
                    'type' => 'DATE',
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
            $this->forge->addUniqueKey(['rider_id', 'effective_date']);
            $this->forge->addForeignKey('rider_id', 'riders', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('rider_commission_rates');
        }

        $riders = $this->db->table('riders')->get()->getResultArray();
        foreach ($riders as $rider) {
            $exists = $this->db->table('rider_commission_rates')
                ->where('rider_id', (int) $rider['id'])
                ->countAllResults();

            if ($exists === 0) {
                $createdAt = ! empty($rider['created_at']) ? substr((string) $rider['created_at'], 0, 10) : date('Y-m-d');
                $this->db->table('rider_commission_rates')->insert([
                    'rider_id' => (int) $rider['id'],
                    'commission_rate' => (float) $rider['commission_rate'],
                    'effective_date' => $createdAt,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('rider_commission_rates')) {
            $this->forge->dropTable('rider_commission_rates', true);
        }
    }
}
