<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryCorrectionRequestModel extends Model
{
    protected $table = 'delivery_correction_requests';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'delivery_record_id',
        'requested_by_user_id',
        'status',
        'reason',
        'requested_payload_json',
        'resolution_note',
        'applied_at',
    ];

    protected array $casts = [
        'delivery_record_id' => '?integer',
        'requested_by_user_id' => '?integer',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
