<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRiderActiveStatus extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_active', 'riders')) {
            $this->forge->addColumn('riders', [
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                    'after' => 'commission_rate',
                ],
            ]);
        }

        $this->db->table('riders')->where('is_active', null)->update(['is_active' => 1]);
    }

    public function down()
    {
        if ($this->db->fieldExists('is_active', 'riders')) {
            $this->forge->dropColumn('riders', 'is_active');
        }
    }
}
