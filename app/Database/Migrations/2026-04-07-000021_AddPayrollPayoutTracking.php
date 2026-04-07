<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPayrollPayoutTracking extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('payrolls')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('payroll_status', 'payrolls')) {
            $fields['payroll_status'] = [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'GENERATED',
                'after' => 'net_pay',
            ];
        }

        if (! $this->db->fieldExists('payout_method', 'payrolls')) {
            $fields['payout_method'] = [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
                'after' => 'payroll_status',
            ];
        }

        if (! $this->db->fieldExists('payout_reference', 'payrolls')) {
            $fields['payout_reference'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'payout_method',
            ];
        }

        if (! $this->db->fieldExists('released_at', 'payrolls')) {
            $fields['released_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'payout_reference',
            ];
        }

        if (! $this->db->fieldExists('released_by_user_id', 'payrolls')) {
            $fields['released_by_user_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'released_at',
            ];
        }

        if (! $this->db->fieldExists('received_at', 'payrolls')) {
            $fields['received_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'released_by_user_id',
            ];
        }

        if (! $this->db->fieldExists('received_notes', 'payrolls')) {
            $fields['received_notes'] = [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'received_at',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('payrolls', $fields);
        }

        if ($this->db->fieldExists('payroll_status', 'payrolls')) {
            $this->db->table('payrolls')
                ->where('payroll_status', null)
                ->set(['payroll_status' => 'GENERATED'])
                ->update();
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('payrolls')) {
            return;
        }

        $dropFields = [];

        foreach ([
            'payroll_status',
            'payout_method',
            'payout_reference',
            'released_at',
            'released_by_user_id',
            'received_at',
            'received_notes',
        ] as $field) {
            if ($this->db->fieldExists($field, 'payrolls')) {
                $dropFields[] = $field;
            }
        }

        if ($dropFields !== []) {
            $this->forge->dropColumn('payrolls', $dropFields);
        }
    }
}
