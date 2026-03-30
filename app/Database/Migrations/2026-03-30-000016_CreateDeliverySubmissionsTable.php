<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeliverySubmissionsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('delivery_submissions')) {
            return;
        }

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
            'delivery_date' => [
                'type' => 'DATE',
            ],
            'allocated_parcels' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'successful_deliveries' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'failed_deliveries' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'expected_remittance' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['PENDING', 'APPROVED', 'REJECTED'],
                'default' => 'PENDING',
            ],
            'processed_delivery_record_id' => [
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
        $this->forge->addKey(['rider_id', 'delivery_date']);
        $this->forge->addForeignKey('rider_id', 'riders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('processed_delivery_record_id', 'delivery_records', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('delivery_submissions');
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_submissions')) {
            $this->forge->dropTable('delivery_submissions', true);
        }
    }
}
