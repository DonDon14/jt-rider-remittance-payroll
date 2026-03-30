<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowPendingRemittanceStatus extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE remittances MODIFY variance_type ENUM('SHORT', 'OVER', 'BALANCED', 'PENDING') NOT NULL DEFAULT 'PENDING'");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE remittances MODIFY variance_type ENUM('SHORT', 'OVER', 'BALANCED') NOT NULL DEFAULT 'BALANCED'");
    }
}
