<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliverySubmissionModel extends Model
{
    protected $table = 'delivery_submissions';
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
        'notes',
        'status',
        'processed_delivery_record_id',
    ];

    protected array $casts = [
        'rider_id' => '?integer',
        'allocated_parcels' => '?integer',
        'successful_deliveries' => '?integer',
        'failed_deliveries' => '?integer',
        'expected_remittance' => '?float',
        'processed_delivery_record_id' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
