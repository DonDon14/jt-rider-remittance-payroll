<?php

namespace App\Models;

use CodeIgniter\Model;

class RemittanceAccountModel extends Model
{
    protected $table = 'remittance_accounts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'account_name',
        'account_number',
        'description',
        'sort_order',
        'is_active',
    ];

    protected array $casts = [
        'sort_order' => '?integer',
        'is_active' => 'boolean',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
