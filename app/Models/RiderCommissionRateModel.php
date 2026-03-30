<?php

namespace App\Models;

use CodeIgniter\Model;

class RiderCommissionRateModel extends Model
{
    protected $table = 'rider_commission_rates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'rider_id',
        'commission_rate',
        'effective_date',
    ];

    protected array $casts = [
        'rider_id' => '?integer',
        'commission_rate' => '?float',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
