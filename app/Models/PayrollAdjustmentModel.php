<?php

namespace App\Models;

use CodeIgniter\Model;

class PayrollAdjustmentModel extends Model
{
    protected $table = 'payroll_adjustments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'rider_id',
        'adjustment_date',
        'type',
        'amount',
        'description',
        'batch_reference',
        'payroll_id',
    ];

    protected array $casts = [
        'rider_id' => '?integer',
        'amount' => '?float',
        'payroll_id' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
