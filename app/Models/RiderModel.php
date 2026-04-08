<?php

namespace App\Models;

use CodeIgniter\Model;

class RiderModel extends Model
{
    protected $table = 'riders';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'rider_code',
        'name',
        'contact_number',
        'profile_photo_path',
        'address',
        'birth_date',
        'emergency_contact_name',
        'emergency_contact_number',
        'government_id_number',
        'hire_date',
        'branch_name',
        'notes',
        'commission_rate',
        'is_active',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'commission_rate' => '?float',
        'is_active' => 'boolean',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}

