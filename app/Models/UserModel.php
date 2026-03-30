<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'username',
        'password_hash',
        'role',
        'rider_id',
        'is_active',
        'last_seen_announcement_id',
    ];

    protected array $casts = [
        'rider_id' => '?integer',
        'is_active' => 'boolean',
        'last_seen_announcement_id' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
