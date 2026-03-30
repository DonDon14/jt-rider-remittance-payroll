<?php

namespace App\Models;

use CodeIgniter\Model;

class ShortagePaymentModel extends Model
{
    protected $table = 'shortage_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'remittance_id',
        'rider_id',
        'payment_date',
        'amount',
        'notes',
        'payroll_id',
    ];

    protected array $casts = [
        'remittance_id' => '?integer',
        'rider_id' => '?integer',
        'amount' => '?float',
        'payroll_id' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
