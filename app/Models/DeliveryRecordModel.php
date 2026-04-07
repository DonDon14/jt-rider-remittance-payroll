<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryRecordModel extends Model
{
    protected $table = 'delivery_records';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'rider_id',
        'delivery_date',
        'allocated_parcels',
        'successful_deliveries',
        'failed_deliveries',
        'expected_remittance',
        'remittance_account_id',
        'commission_rate',
        'total_due',
        'notes',
        'entry_source',
        'source_submission_id',
        'created_by_user_id',
        'last_admin_reason',
        'payroll_id',
    ];

    protected array $casts = [
        'rider_id' => '?integer',
        'allocated_parcels' => '?integer',
        'successful_deliveries' => '?integer',
        'failed_deliveries' => '?integer',
        'expected_remittance' => '?float',
        'remittance_account_id' => '?integer',
        'commission_rate' => '?float',
        'total_due' => '?float',
        'source_submission_id' => '?integer',
        'created_by_user_id' => '?integer',
        'payroll_id' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
