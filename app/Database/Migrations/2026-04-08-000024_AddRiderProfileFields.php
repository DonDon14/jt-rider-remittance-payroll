<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRiderProfileFields extends Migration
{
    public function up()
    {
        $fields = [
            'profile_photo_path' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'contact_number',
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'profile_photo_path',
            ],
            'birth_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'address',
            ],
            'emergency_contact_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'birth_date',
            ],
            'emergency_contact_number' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
                'after' => 'emergency_contact_name',
            ],
            'government_id_number' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'emergency_contact_number',
            ],
            'hire_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'government_id_number',
            ],
            'branch_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'hire_date',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'branch_name',
            ],
        ];

        $this->forge->addColumn('riders', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('riders', [
            'profile_photo_path',
            'address',
            'birth_date',
            'emergency_contact_name',
            'emergency_contact_number',
            'government_id_number',
            'hire_date',
            'branch_name',
            'notes',
        ]);
    }
}
