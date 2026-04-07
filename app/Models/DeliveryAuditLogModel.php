<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryAuditLogModel extends Model
{
    protected $table = 'delivery_audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'delivery_record_id',
        'delivery_submission_id',
        'rider_id',
        'actor_user_id',
        'actor_role',
        'action',
        'notes',
        'details_json',
        'created_at',
    ];

    protected array $casts = [
        'delivery_record_id' => '?integer',
        'delivery_submission_id' => '?integer',
        'rider_id' => '?integer',
        'actor_user_id' => '?integer',
    ];

    protected $useTimestamps = false;
}
