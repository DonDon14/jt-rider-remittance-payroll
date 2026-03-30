<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCoreTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'rider_code' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
            ],
            'contact_number' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
            ],
            'commission_rate' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 13.00,
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
        $this->forge->addUniqueKey('rider_code');
        $this->forge->createTable('riders');

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
            'total_due' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'notes' => [
                'type' => 'TEXT',
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
        $this->forge->createTable('delivery_records');

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
            'delivery_record_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'delivery_date' => [
                'type' => 'DATE',
            ],
            'denom_025' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_1' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_5' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_10' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_20' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_50' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_100' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_500' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'denom_1000' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'total_due' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'total_remitted' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'variance_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'variance_type' => [
                'type' => 'ENUM',
                'constraint' => ['SHORT', 'OVER', 'BALANCED'],
                'default' => 'BALANCED',
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
        $this->forge->addUniqueKey('delivery_record_id');
        $this->forge->addForeignKey('rider_id', 'riders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('delivery_record_id', 'delivery_records', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('remittances');

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
            'month_year' => [
                'type' => 'DATE',
            ],
            'total_successful' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'gross_earnings' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'total_due' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'total_remitted' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'remittance_variance' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
            ],
            'net_pay' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'default' => 0,
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
        $this->forge->addUniqueKey(['rider_id', 'month_year']);
        $this->forge->addForeignKey('rider_id', 'riders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('payrolls');
    }

    public function down()
    {
        $this->forge->dropTable('payrolls', true);
        $this->forge->dropTable('remittances', true);
        $this->forge->dropTable('delivery_records', true);
        $this->forge->dropTable('riders', true);
    }
}
