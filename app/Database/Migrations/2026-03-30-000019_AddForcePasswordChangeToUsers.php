<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddForcePasswordChangeToUsers extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('users') && ! $this->db->fieldExists('force_password_change', 'users')) {
            $this->forge->addColumn('users', [
                'force_password_change' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                    'after' => 'is_active',
                ],
            ]);
        }

        if ($this->db->tableExists('users') && $this->db->fieldExists('force_password_change', 'users')) {
            $this->db->table('users')->set(['force_password_change' => 1])->update();
        }
    }

    public function down()
    {
        if ($this->db->tableExists('users') && $this->db->fieldExists('force_password_change', 'users')) {
            $this->forge->dropColumn('users', 'force_password_change');
        }
    }
}
