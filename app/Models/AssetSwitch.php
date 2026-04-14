<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetSwitch extends Model
{
  protected $table = 'asset_switch';
  protected $fillable = [
    'hostname',
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
