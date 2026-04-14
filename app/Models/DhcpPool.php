<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DhcpPool extends Model
{
  protected $table = 'dhcp_pools';

  protected $fillable = [
    'site',
    'name',
    'network',
    'netmask',
    'dns_list',
    'gateway_list',
    'forbidden_ip',
    'lease_seconds',
    'options',
    'remark',
  ];

  protected $casts = [
    'dns_list' => 'array',
    'gateway_list' => 'array',
    'options' => 'array',
  ];

  public function vlans()
  {
    return $this->hasMany(Vlan::class, 'dhcp_pool_id');
  }
}
