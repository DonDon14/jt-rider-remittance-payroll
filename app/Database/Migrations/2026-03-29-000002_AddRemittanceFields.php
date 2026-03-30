<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRemittanceFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('remittances', [
            'supposed_remittance' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'null' => true,
                'after' => 'total_due',
            ],
            'actual_remitted' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'null' => true,
                'after' => 'supposed_remittance',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('remittances', ['supposed_remittance', 'actual_remitted']);
    }
}
