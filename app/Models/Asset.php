<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'hostname',
        'category',
        'type',
        'group',
        'ip_address',
        'mac_address',
        'serial_number',
        'end_of_support',
        'warranty',
        'firmware_version',
        'location',
        'floor',
        'tower',
        'credential_id',
        'remark',
    ];

    // Relasi ke Credential (satu asset punya satu credential)
    public function credential()
    {
        return $this->belongsTo(Credential::class, 'credential_id');
    }
}
