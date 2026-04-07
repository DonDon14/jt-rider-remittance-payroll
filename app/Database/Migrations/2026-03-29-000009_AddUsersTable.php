<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUsersTable extends Migration
{
    public function up()
    {
        helper('credentials');
        $bootstrapAdminPassword = trim((string) env('auth.bootstrapAdminPassword'));

        if (! $this->db->tableExists('users')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'username' => [
                    'type' => 'VARCHAR',
                    'constraint' => 80,
                ],
                'password_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'role' => [
                    'type' => 'ENUM',
                    'constraint' => ['admin', 'rider'],
                    'default' => 'rider',
                ],
                'rider_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
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
            $this->forge->addUniqueKey('username');
            $this->forge->addForeignKey('rider_id', 'riders', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('users');
        }

        $adminExists = $this->db->table('users')->where('username', 'admin')->countAllResults();
        if ($adminExists === 0) {
            if ($bootstrapAdminPassword === '') {
                throw new \RuntimeException('Set auth.bootstrapAdminPassword in your environment before running migrations for a fresh install.');
            }

            $this->db->table('users')->insert([
                'username' => 'admin',
                'password_hash' => password_hash($bootstrapAdminPassword, PASSWORD_DEFAULT),
                'role' => 'admin',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $riders = $this->db->table('riders')->get()->getResultArray();
        foreach ($riders as $rider) {
            $username = strtolower((string) $rider['rider_code']);
            $exists = $this->db->table('users')->where('username', $username)->countAllResults();
            if ($exists === 0) {
                $temporaryPassword = app_generate_temporary_password();
                $this->db->table('users')->insert([
                    'username' => $username,
                    'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    'role' => 'rider',
                    'rider_id' => (int) $rider['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('users')) {
            $this->forge->dropTable('users', true);
        }
    }
}
