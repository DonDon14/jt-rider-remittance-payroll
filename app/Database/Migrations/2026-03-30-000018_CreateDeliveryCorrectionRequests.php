<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeliveryCorrectionRequests extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('delivery_correction_requests')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'delivery_record_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                ],
                'requested_by_user_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => 'PENDING',
                ],
                'reason' => [
                    'type' => 'TEXT',
                ],
                'requested_payload_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'resolution_note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'applied_at' => [
                    'type' => 'DATETIME',
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
            $this->forge->addKey('delivery_record_id');
            $this->forge->createTable('delivery_correction_requests');
        }
    }

    public function down()
    {
        $this->forge->dropTable('delivery_correction_requests', true);
    }
}
