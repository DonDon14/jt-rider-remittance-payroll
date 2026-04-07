<?php

namespace App\Models;

use CodeIgniter\Model;

class PayrollModel extends Model
{
    protected $table = 'payrolls';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'rider_id',
        'month_year',
        'start_date',
        'end_date',
        'total_successful',
        'gross_earnings',
        'total_due',
        'total_remitted',
        'remittance_variance',
        'shortage_deductions',
        'shortage_payments_received',
        'bonus_total',
        'deduction_total',
        'outstanding_shortage_balance',
        'net_pay',
        'payroll_status',
        'payout_method',
        'payout_reference',
        'released_at',
        'released_by_user_id',
        'received_at',
        'received_notes',
    ];

    protected array $casts = [
        'rider_id' => '?integer',
        'total_successful' => '?integer',
        'gross_earnings' => '?float',
        'total_due' => '?float',
        'total_remitted' => '?float',
        'remittance_variance' => '?float',
        'shortage_deductions' => '?float',
        'shortage_payments_received' => '?float',
        'bonus_total' => '?float',
        'deduction_total' => '?float',
        'outstanding_shortage_balance' => '?float',
        'net_pay' => '?float',
        'released_by_user_id' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
