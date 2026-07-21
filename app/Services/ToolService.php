<?php

namespace App\Services;

use App\Models\DhcpPool;
use App\Models\IpAddress;
use App\Models\Vlan;
use Illuminate\Database\Eloquent\Builder;

class ToolService
{
  public function detectIntent(string $text): string
  {
    $text = $this->normalizeText($text);

    if ($this->isDhcpPoolIntent($text)) {
      return 'dhcp_pool';
    }

    if ($this->isVlanIntent($text)) {
      return 'vlan';
    }

    if ($this->isIpAddressIntent($text)) {
      return 'ip_address';
    }

    return 'general_chat';
  }

  protected function isIpAddressIntent(string $text): bool
  {
    return str_contains($text, 'ip address')
      || str_contains($text, 'alamat ip')
      || str_contains($text, 'daftar ip')
      || str_contains($text, 'data ip')
      || str_contains($text, 'ip berapa')
      || str_contains($text, 'ip mana')
      || str_contains($text, 'cari ip')
      || str_contains($text, 'cek ip')
      || $this->extractIpAddress($text) !== null;
  }

  protected function isVlanIntent(string $text): bool
  {
    return str_contains($text, 'vlan')
      || str_contains($text, 'virtual lan')
      || str_contains($text, 'virtual local area network');
  }

  protected function isDhcpPoolIntent(string $text): bool
  {
    return str_contains($text, 'dhcp pool')
      || str_contains($text, 'dhcppool')
      || str_contains($text, 'pool dhcp')
      || str_contains($text, 'dhcp')
      || str_contains($text, 'lease dhcp');
  }

  public function extractIpAddress(string $text): ?string
  {
    $matched = preg_match(
      '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
      $text,
      $matches
    );

    if ($matched !== 1) {
      return null;
    }

    $ip = $matches[0];

    return filter_var(
      $ip,
      FILTER_VALIDATE_IP,
      FILTER_FLAG_IPV4
    ) !== false
      ? $ip
      : null;
  }

  public function extractCidr(string $text): ?string
  {
    $matched = preg_match(
      '/\b(?:\d{1,3}\.){3}\d{1,3}\/(?:[0-9]|[12][0-9]|3[0-2])\b/',
      $text,
      $matches
    );

    if ($matched !== 1) {
      return null;
    }

    [$ip, $prefix] = explode('/', $matches[0], 2);

    if (
      filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_IPV4
      ) === false
    ) {
      return null;
    }

    return $ip . '/' . (int) $prefix;
  }

  public function extractVlanId(string $text): ?int
  {
    $patterns = [
      '/\bvlan\s*(?:id\s*)?[:#-]?\s*(\d{1,4})\b/i',
      '/\bvid\s*[:#-]?\s*(\d{1,4})\b/i',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $text, $matches) === 1) {
        $vlanId = (int) $matches[1];

        if ($vlanId >= 0 && $vlanId <= 4094) {
          return $vlanId;
        }
      }
    }

    return null;
  }

  public function findIpAddress(string $ip): ?IpAddress
  {
    return IpAddress::query()
      ->with('vlan.dhcpPool')
      ->where('ip', $ip)
      ->first();
  }

  public function getIpAddresses(
    ?string $keyword = null,
    int $limit = 20
  ): array {
    $query = IpAddress::query()
      ->with('vlan.dhcpPool');

    if (filled($keyword)) {
      $query->where(function (Builder $query) use ($keyword): void {
        $query
          ->where('ip', 'like', '%' . $keyword . '%')
          ->orWhere(
            'description',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhereHas(
            'vlan',
            function (Builder $vlanQuery) use ($keyword): void {
              $vlanQuery
                ->where(
                  'name',
                  'like',
                  '%' . $keyword . '%'
                )
                ->orWhere(
                  'site',
                  'like',
                  '%' . $keyword . '%'
                )
                ->orWhere(
                  'network',
                  'like',
                  '%' . $keyword . '%'
                )
                ->orWhere(
                  'remark',
                  'like',
                  '%' . $keyword . '%'
                );
            }
          );
      });
    }

    return $query
      ->orderByRaw('INET_ATON(ip) IS NULL, INET_ATON(ip)')
      ->limit($this->normalizeLimit($limit))
      ->get()
      ->map(fn(IpAddress $ipAddress): array => [
        'id' => $ipAddress->getKey(),
        'ip' => $ipAddress->ip,
        'description' => $ipAddress->description,
        'vlan_id' => $ipAddress->vlan?->vlan_id,
        'vlan_name' => $ipAddress->vlan?->name,
        'vlan_network' => $ipAddress->vlan?->network,
        'site' => $ipAddress->vlan?->site,
        'dhcp_pool' => $ipAddress->vlan?->dhcpPool?->name,
      ])
      ->values()
      ->all();
  }

  public function findVlanByVlanId(int $vlanId): ?Vlan
  {
    return Vlan::query()
      ->with([
        'dhcpPool',
      ])
      ->where('vlan_id', $vlanId)
      ->first();
  }

  public function getVlans(
    ?string $keyword = null,
    ?int $vlanId = null,
    ?string $network = null,
    int $limit = 20
  ): array {
    $query = Vlan::query()
      ->with('dhcpPool');

    if ($vlanId !== null) {
      $query->where('vlan_id', $vlanId);
    }

    if (filled($network)) {
      $query->where('network', $network);
    }

    if (filled($keyword)) {
      $query->where(function (Builder $query) use ($keyword): void {
        $query
          ->where(
            'name',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'network',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'gateway',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'remark',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'site',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhereHas(
            'dhcpPool',
            function (Builder $poolQuery) use ($keyword): void {
              $poolQuery
                ->where(
                  'name',
                  'like',
                  '%' . $keyword . '%'
                )
                ->orWhere(
                  'site',
                  'like',
                  '%' . $keyword . '%'
                )
                ->orWhere(
                  'network',
                  'like',
                  '%' . $keyword . '%'
                );
            }
          );
      });
    }

    return $query
      ->orderBy('site')
      ->orderBy('vlan_id')
      ->limit($this->normalizeLimit($limit))
      ->get()
      ->map(fn(Vlan $vlan): array => [
        'id' => $vlan->getKey(),
        'vlan_id' => $vlan->vlan_id,
        'name' => $vlan->name,
        'network' => $vlan->network,
        'netmask' => $vlan->netmask,
        'gateway' => $vlan->gateway,
        'dhcp' => $vlan->dhcp,
        'client' => $vlan->client,
        'start_ip' => $vlan->start_ip,
        'last_ip' => $vlan->last_ip,
        'site' => $vlan->site,
        'remark' => $vlan->remark,
        'dhcp_pool_id' => $vlan->dhcp_pool_id,
        'dhcp_pool_name' => $vlan->dhcpPool?->name,
      ])
      ->values()
      ->all();
  }

  public function findDhcpPoolByName(string $name): ?DhcpPool
  {
    return DhcpPool::query()
      ->with('vlans')
      ->whereRaw('LOWER(name) = ?', [
        mb_strtolower(trim($name)),
      ])
      ->first();
  }

  public function getDhcpPools(
    ?string $keyword = null,
    ?string $network = null,
    int $limit = 20
  ): array {
    $query = DhcpPool::query()
      ->with([
        'vlans',
      ]);

    if (filled($network)) {
      $query->where('network', $network);
    }

    if (filled($keyword)) {
      $query->where(function (Builder $query) use ($keyword): void {
        $query
          ->where(
            'name',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'site',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'network',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'netmask',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'forbidden_ip',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhere(
            'remark',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhereHas(
            'vlans',
            function (Builder $vlanQuery) use ($keyword): void {
              $vlanQuery
                ->where(
                  'name',
                  'like',
                  '%' . $keyword . '%'
                )
                ->orWhere(
                  'site',
                  'like',
                  '%' . $keyword . '%'
                )
                ->orWhere(
                  'network',
                  'like',
                  '%' . $keyword . '%'
                );
            }
          );
      });
    }

    return $query
      ->orderBy('site')
      ->orderBy('name')
      ->limit($this->normalizeLimit($limit))
      ->get()
      ->map(fn(DhcpPool $pool): array => [
        'id' => $pool->getKey(),
        'site' => $pool->site,
        'name' => $pool->name,
        'network' => $pool->network,
        'netmask' => $pool->netmask,
        'dns_list' => $pool->dns_list ?? [],
        'gateway_list' => $pool->gateway_list ?? [],
        'forbidden_ip' => $pool->forbidden_ip,
        'lease_seconds' => $pool->lease_seconds,
        'lease_human' => $this->formatLeaseSeconds(
          $pool->lease_seconds
        ),
        'options' => $pool->options ?? [],
        'remark' => $pool->remark,
        'vlans' => $pool->vlans
          ->map(fn(Vlan $vlan): array => [
            'vlan_id' => $vlan->vlan_id,
            'name' => $vlan->name,
            'network' => $vlan->network,
            'gateway' => $vlan->gateway,
            'site' => $vlan->site,
          ])
          ->values()
          ->all(),
      ])
      ->values()
      ->all();
  }

  public function extractSearchKeyword(
    string $text,
    string $intent
  ): ?string {
    $text = $this->normalizeText($text);

    $exactIp = $this->extractIpAddress($text);
    $cidr = $this->extractCidr($text);
    $vlanId = $this->extractVlanId($text);

    if ($exactIp !== null) {
      $text = str_replace($exactIp, ' ', $text);
    }

    if ($cidr !== null) {
      $text = str_replace($cidr, ' ', $text);
    }

    if ($vlanId !== null) {
      $patterns = [
        '/\bvlan\s*(?:id\s*)?[:#-]?\s*'
        . preg_quote((string) $vlanId, '/')
        . '\b/i',
        '/\bvid\s*[:#-]?\s*'
        . preg_quote((string) $vlanId, '/')
        . '\b/i',
      ];

      foreach ($patterns as $pattern) {
        $text = preg_replace($pattern, ' ', $text);
      }
    }

    $ignoredWords = [
      'tolong',
      'cari',
      'cek',
      'lihat',
      'tampilkan',
      'tampil',
      'daftar',
      'data',
      'detail',
      'informasi',
      'semua',
      'yang',
      'untuk',
      'punya',
      'berapa',
      'mana',
      'ada',
      'di',
      'pada',
      'dari',
      'berdasarkan',
    ];

    if ($intent === 'ip_address') {
      $ignoredWords = array_merge($ignoredWords, [
        'ip',
        'address',
        'alamat',
      ]);
    }

    if ($intent === 'vlan') {
      $ignoredWords = array_merge($ignoredWords, [
        'vlan',
        'virtual',
        'lan',
        'virtual local area network',
      ]);
    }

    if ($intent === 'dhcp_pool') {
      $ignoredWords = array_merge($ignoredWords, [
        'dhcp',
        'pool',
        'dhcppool',
        'lease',
      ]);
    }

    foreach ($ignoredWords as $ignoredWord) {
      $text = preg_replace(
        '/\b' . preg_quote($ignoredWord, '/') . '\b/ui',
        ' ',
        $text
      );
    }

    $text = preg_replace('/\s+/u', ' ', $text);
    $keyword = trim((string) $text);

    return $keyword !== '' ? $keyword : null;
  }

  protected function normalizeText(string $text): string
  {
    $text = mb_strtolower(trim($text));

    return preg_replace('/\s+/u', ' ', $text) ?: '';
  }

  protected function normalizeLimit(int $limit): int
  {
    return max(1, min($limit, 50));
  }

  protected function formatLeaseSeconds(
    int|string|null $seconds
  ): string {
    if ($seconds === null || !is_numeric($seconds)) {
      return '-';
    }

    $seconds = (int) $seconds;

    if ($seconds <= 0) {
      return '0 detik';
    }

    $days = intdiv($seconds, 86400);
    $seconds %= 86400;

    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;

    $minutes = intdiv($seconds, 60);
    $seconds %= 60;

    $parts = [];

    if ($days > 0) {
      $parts[] = "{$days} hari";
    }

    if ($hours > 0) {
      $parts[] = "{$hours} jam";
    }

    if ($minutes > 0) {
      $parts[] = "{$minutes} menit";
    }

    if ($seconds > 0) {
      $parts[] = "{$seconds} detik";
    }

    return implode(' ', $parts);
  }
}
