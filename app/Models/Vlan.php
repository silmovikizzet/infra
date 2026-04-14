<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vlan extends Model
{
  protected $table = 'vlans';

  protected $fillable = [
    'dhcp',
    'vlan_id',
    'name',
    'network',
    'gateway',
    'remark',
    'site',
    'dhcp_pool_id',
  ];

  // supaya tetap bisa dipanggil $vlan->client / start_ip / last_ip
  protected $appends = ['client', 'start_ip', 'last_ip', 'netmask'];

  public function getClientAttribute()
  {
    return $this->calcFromCidr($this->network)['client'];
  }

  public function getStartIpAttribute()
  {
    return $this->calcFromCidr($this->network)['start_ip'];
  }

  public function getLastIpAttribute()
  {
    return $this->calcFromCidr($this->network)['last_ip'];
  }

  public function getNetmaskAttribute()
  {
    $cidr = trim((string) ($this->network ?? ''));
    if ($cidr === '' || !str_contains($cidr, '/'))
      return null;

    [, $prefix] = explode('/', $cidr, 2);
    $prefix = (int) $prefix;
    if ($prefix < 0 || $prefix > 32)
      return null;

    $mask = ($prefix === 0) ? 0 : ((~0 << (32 - $prefix)) & 0xFFFFFFFF);
    return long2ip((int) sprintf('%u', $mask));
  }

  private function calcFromCidr(?string $cidr): array
  {
    $cidr = trim((string) $cidr);
    if ($cidr === '' || !str_contains($cidr, '/')) {
      return ['client' => null, 'start_ip' => null, 'last_ip' => null];
    }

    [$ip, $prefix] = explode('/', $cidr, 2);
    $prefix = (int) $prefix;

    $ipLong = ip2long($ip);
    if ($ipLong === false || $prefix < 0 || $prefix > 32) {
      return ['client' => null, 'start_ip' => null, 'last_ip' => null];
    }

    $mask = ($prefix === 0) ? 0 : ((~0 << (32 - $prefix)) & 0xFFFFFFFF);
    $network = ($ipLong & $mask);
    $broadcast = $network + (2 ** (32 - $prefix)) - 1;

    $firstHost = $network + 1;
    $lastHost = $broadcast - 1;

    if ($lastHost < $firstHost) {
      return ['client' => 0, 'start_ip' => null, 'last_ip' => null];
    }

    return [
      'client' => ($lastHost - $firstHost + 1),
      'start_ip' => long2ip($firstHost),
      'last_ip' => long2ip($lastHost),
    ];
  }

  public function updatedNetwork($value): void
  {
    if ($this->syncing)
      return;
    $this->syncing = true;

    $value = trim((string) $value);
    if ($value === '') {
      $this->netmask = '';
      $this->syncing = false;
      return;
    }

    // bentuk: IP/prefix
    if (str_contains($value, '/')) {
      [$ip, $prefix] = array_pad(explode('/', $value, 2), 2, '');
      $ip = trim($ip);
      $prefix = (int) trim($prefix);

      if ($this->isValidIpv4($ip) && $prefix >= 0 && $prefix <= 32) {
        $this->netmask = $this->prefixToNetmask($prefix);
      } else {
        // invalid → jangan paksa apa-apa
        $this->netmask = '';
      }

      $this->syncing = false;
      return;
    }

    // bentuk: IP saja → kalau netmask sudah valid, ubah network jadi IP/prefix
    $ip = $value;
    if ($this->isValidIpv4($ip)) {
      $prefix = $this->netmaskToPrefix((string) $this->netmask);
      if ($prefix !== null) {
        $this->setNetworkPrefix($ip, $prefix);
      }
    }

    $this->syncing = false;
  }

  public function updatedNetmask($value): void
  {
    if ($this->syncing)
      return;
    $this->syncing = true;

    $mask = trim((string) $value);
    if ($mask === '') {
      // kalau user hapus netmask, jangan ubah network
      $this->syncing = false;
      return;
    }

    $prefix = $this->netmaskToPrefix($mask);
    if ($prefix === null) {
      // netmask gak valid → jangan ubah network
      $this->syncing = false;
      return;
    }

    $net = trim((string) $this->network);
    if ($net === '') {
      $this->syncing = false;
      return;
    }

    // kalau network sudah CIDR, ganti prefix-nya
    if (str_contains($net, '/')) {
      [$ip] = explode('/', $net, 2);
      $ip = trim($ip);
      if ($this->isValidIpv4($ip)) {
        $this->setNetworkPrefix($ip, $prefix);
      }
      $this->syncing = false;
      return;
    }

    // kalau network cuma IP, jadikan CIDR
    if ($this->isValidIpv4($net)) {
      $this->setNetworkPrefix($net, $prefix);
    }

    $this->syncing = false;
  }

  private function prefixToNetmask(int $prefix): string
  {
    // prefix 0 => 0.0.0.0
    $mask = ($prefix === 0) ? 0 : ((~0 << (32 - $prefix)) & 0xFFFFFFFF);
    // sprintf %u biar unsigned 32-bit kebaca benar
    return long2ip((int) sprintf('%u', $mask));
  }

  public function dhcpPool()
  {
    return $this->belongsTo(DhcpPool::class, 'dhcp_pool_id');
  }
  public function credential()
  {
    return $this->belongsTo(\App\Models\Credential::class);
  }
}
