<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUniqueDeliveryRecordPerDay extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE delivery_records ADD CONSTRAINT delivery_records_rider_date_unique UNIQUE (rider_id, delivery_date)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE delivery_records DROP INDEX delivery_records_rider_date_unique');
    }
}
