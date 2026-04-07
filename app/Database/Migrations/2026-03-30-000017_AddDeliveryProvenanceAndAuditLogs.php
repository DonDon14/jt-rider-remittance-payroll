<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeliveryProvenanceAndAuditLogs extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('delivery_records')) {
            $fields = [];

            if (! $this->db->fieldExists('entry_source', 'delivery_records')) {
                $fields['entry_source'] = [
                    'type' => 'ENUM',
                    'constraint' => ['ADMIN_MANUAL', 'RIDER_SUBMISSION'],
                    'default' => 'ADMIN_MANUAL',
                    'after' => 'notes',
                ];
            }

            if (! $this->db->fieldExists('source_submission_id', 'delivery_records')) {
                $fields['source_submission_id'] = [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'entry_source',
                ];
            }

            if (! $this->db->fieldExists('created_by_user_id', 'delivery_records')) {
                $fields['created_by_user_id'] = [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'source_submission_id',
                ];
            }

            if (! $this->db->fieldExists('last_admin_reason', 'delivery_records')) {
                $fields['last_admin_reason'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'created_by_user_id',
                ];
            }

            if ($fields !== []) {
                $this->forge->addColumn('delivery_records', $fields);
            }
        }

        if ($this->db->tableExists('delivery_records')) {
            $this->db->query("UPDATE delivery_records SET entry_source = 'ADMIN_MANUAL' WHERE entry_source IS NULL OR entry_source = ''");
        }

        if (! $this->db->tableExists('delivery_audit_logs')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'delivery_record_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'delivery_submission_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'rider_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'actor_user_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'actor_role' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'null' => true,
                ],
                'action' => [
                    'type' => 'VARCHAR',
                    'constraint' => 60,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'details_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['delivery_record_id', 'delivery_submission_id', 'rider_id']);
            $this->forge->createTable('delivery_audit_logs');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_audit_logs')) {
            $this->forge->dropTable('delivery_audit_logs', true);
        }

        if ($this->db->tableExists('delivery_records')) {
            foreach (['last_admin_reason', 'created_by_user_id', 'source_submission_id', 'entry_source'] as $field) {
                if ($this->db->fieldExists($field, 'delivery_records')) {
                    $this->forge->dropColumn('delivery_records', $field);
                }
            }
        }
    }
}
