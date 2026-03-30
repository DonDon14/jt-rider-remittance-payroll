<?php

namespace App\Models;

use CodeIgniter\Model;

class RemittanceModel extends Model
{
    protected $table = 'remittances';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'rider_id',
        'delivery_record_id',
        'delivery_date',
        'denom_025',
        'denom_1',
        'denom_5',
        'denom_10',
        'denom_20',
        'denom_50',
        'denom_100',
        'denom_500',
        'denom_1000',
        'total_due',
        'total_remitted',
        'supposed_remittance',
        'actual_remitted',
        'variance_amount',
        'variance_type',
    ];

    protected array $casts = [
        'rider_id' => '?integer',
        'delivery_record_id' => '?integer',
        'denom_025' => '?integer',
        'denom_1' => '?integer',
        'denom_5' => '?integer',
        'denom_10' => '?integer',
        'denom_20' => '?integer',
        'denom_50' => '?integer',
        'denom_100' => '?integer',
        'denom_500' => '?integer',
        'denom_1000' => '?integer',
        'total_due' => '?float',
        'total_remitted' => '?float',
        'supposed_remittance' => '?float',
        'actual_remitted' => '?float',
        'variance_amount' => '?float',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
