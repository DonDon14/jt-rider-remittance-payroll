<?php

namespace App\Models;

use CodeIgniter\Model;

class RemittanceEntryModel extends Model
{
    protected $table = 'remittance_entries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'remittance_id',
        'remittance_account_id',
        'entry_type',
        'entry_sequence',
        'cash_remitted',
        'gcash_remitted',
        'gcash_reference',
        'denom_025',
        'denom_1',
        'denom_5',
        'denom_10',
        'denom_20',
        'denom_50',
        'denom_100',
        'denom_500',
        'denom_1000',
        'total_remitted',
        'notes',
        'created_by_user_id',
    ];

    protected array $casts = [
        'remittance_id' => '?integer',
        'remittance_account_id' => '?integer',
        'entry_sequence' => '?integer',
        'cash_remitted' => '?float',
        'gcash_remitted' => '?float',
        'denom_025' => '?integer',
        'denom_1' => '?integer',
        'denom_5' => '?integer',
        'denom_10' => '?integer',
        'denom_20' => '?integer',
        'denom_50' => '?integer',
        'denom_100' => '?integer',
        'denom_500' => '?integer',
        'denom_1000' => '?integer',
        'total_remitted' => '?float',
        'created_by_user_id' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
