<?php

namespace App\Services;

use App\Models\IpAddress;

class AIService
{
  public function __construct(
    protected OllamaService $ollama,
    protected ToolService $tools,
  ) {
  }

  public function handleMessage(string $text): string
  {
    $intent = $this->tools->detectIntent($text);

    return match ($intent) {
      'ip_address' => $this->handleIpAddressQuestion($text),
      'vlan' => $this->handleVlanQuestion($text),
      'dhcp_pool' => $this->handleDhcpPoolQuestion($text),
      default => $this->ollama->chat($text),
    };
  }

  protected function handleIpAddressQuestion(
    string $text
  ): string {
    $exactIp = $this->tools->extractIpAddress($text);

    if ($exactIp !== null) {
      $ipAddress = $this->tools->findIpAddress($exactIp);

      if (!$ipAddress) {
        return "IP {$exactIp} tidak ditemukan di database.";
      }

      return $this->formatSingleIpAddress($ipAddress);
    }

    $keyword = $this->tools->extractSearchKeyword(
      $text,
      'ip_address'
    );

    $ipAddresses = $this->tools->getIpAddresses(
      keyword: $keyword,
      limit: 20
    );

    if ($ipAddresses === []) {
      return $keyword !== null
        ? "Tidak ada IP address yang cocok dengan \"{$keyword}\"."
        : 'Tidak ada data IP address di database.';
    }

    $lines = [
      $keyword !== null
      ? "Hasil pencarian IP untuk \"{$keyword}\":"
      : 'Daftar IP address:',
      '',
    ];

    foreach ($ipAddresses as $index => $ipAddress) {
      $number = $index + 1;

      $lines[] = implode("\n", [
        "{$number}. {$ipAddress['ip']}",
        '   Deskripsi: '
        . ($ipAddress['description'] ?: '-'),
        '   VLAN: '
        . ($ipAddress['vlan_id'] ?? '-')
        . ' - '
        . ($ipAddress['vlan_name'] ?: '-'),
        '   Network: '
        . ($ipAddress['vlan_network'] ?: '-'),
        '   Site: '
        . ($ipAddress['site'] ?: '-'),
        '   DHCP Pool: '
        . ($ipAddress['dhcp_pool'] ?: '-'),
      ]);
    }

    if (count($ipAddresses) >= 20) {
      $lines[] = '';
      $lines[] = 'Hasil dibatasi maksimal 20 data.';
    }

    return implode("\n", $lines);
  }

  protected function handleVlanQuestion(
    string $text
  ): string {
    $vlanId = $this->tools->extractVlanId($text);
    $cidr = $this->tools->extractCidr($text);

    if ($vlanId !== null) {
      $vlan = $this->tools->findVlanByVlanId($vlanId);

      if (!$vlan) {
        return "VLAN ID {$vlanId} tidak ditemukan di database.";
      }

      return implode("\n", [
        'Data VLAN ditemukan:',
        '',
        "VLAN ID: {$vlan->vlan_id}",
        'Nama: ' . ($vlan->name ?: '-'),
        'Site: ' . ($vlan->site ?: '-'),
        'Network: ' . ($vlan->network ?: '-'),
        'Netmask: ' . ($vlan->netmask ?: '-'),
        'Gateway: ' . ($vlan->gateway ?: '-'),
        'DHCP: ' . ($vlan->dhcp ? 'Aktif' : 'Tidak aktif'),
        'Kapasitas client: ' . ($vlan->client ?? '-'),
        'IP awal: ' . ($vlan->start_ip ?: '-'),
        'IP akhir: ' . ($vlan->last_ip ?: '-'),
        'DHCP Pool: ' . ($vlan->dhcpPool?->name ?: '-'),
        'Remark: ' . ($vlan->remark ?: '-'),
      ]);
    }

    $keyword = $this->tools->extractSearchKeyword(
      $text,
      'vlan'
    );

    $vlans = $this->tools->getVlans(
      keyword: $keyword,
      vlanId: null,
      network: $cidr,
      limit: 20
    );

    if ($vlans === []) {
      if ($cidr !== null) {
        return "VLAN dengan network {$cidr} tidak ditemukan.";
      }

      return $keyword !== null
        ? "Tidak ada VLAN yang cocok dengan \"{$keyword}\"."
        : 'Tidak ada data VLAN di database.';
    }

    $lines = [
      $keyword !== null
      ? "Hasil pencarian VLAN untuk \"{$keyword}\":"
      : ($cidr !== null
        ? "VLAN dengan network {$cidr}:"
        : 'Daftar VLAN:'),
      '',
    ];

    foreach ($vlans as $index => $vlan) {
      $number = $index + 1;

      $lines[] = implode("\n", [
        "{$number}. VLAN {$vlan['vlan_id']} - "
        . ($vlan['name'] ?: '-'),
        '   Site: ' . ($vlan['site'] ?: '-'),
        '   Network: ' . ($vlan['network'] ?: '-'),
        '   Netmask: ' . ($vlan['netmask'] ?: '-'),
        '   Gateway: ' . ($vlan['gateway'] ?: '-'),
        '   DHCP: ' . ($vlan['dhcp'] ? 'Aktif' : 'Tidak aktif'),
        '   Client: ' . ($vlan['client'] ?? '-'),
        '   Range: '
        . ($vlan['start_ip'] ?: '-')
        . ' - '
        . ($vlan['last_ip'] ?: '-'),
        '   DHCP Pool: '
        . ($vlan['dhcp_pool_name'] ?: '-'),
      ]);
    }

    if (count($vlans) >= 20) {
      $lines[] = '';
      $lines[] = 'Hasil dibatasi maksimal 20 data.';
    }

    return implode("\n", $lines);
  }

  protected function handleDhcpPoolQuestion(
    string $text
  ): string {
    $cidr = $this->tools->extractCidr($text);

    $keyword = $this->tools->extractSearchKeyword(
      $text,
      'dhcp_pool'
    );

    $pools = $this->tools->getDhcpPools(
      keyword: $keyword,
      network: $cidr,
      limit: 20
    );

    if ($pools === []) {
      if ($cidr !== null) {
        return "DHCP Pool dengan network {$cidr} tidak ditemukan.";
      }

      return $keyword !== null
        ? "Tidak ada DHCP Pool yang cocok dengan \"{$keyword}\"."
        : 'Tidak ada data DHCP Pool di database.';
    }

    $lines = [
      $keyword !== null
      ? "Hasil pencarian DHCP Pool untuk \"{$keyword}\":"
      : ($cidr !== null
        ? "DHCP Pool dengan network {$cidr}:"
        : 'Daftar DHCP Pool:'),
      '',
    ];

    foreach ($pools as $index => $pool) {
      $number = $index + 1;

      $dns = $this->formatArrayValue(
        $pool['dns_list'] ?? []
      );

      $gateways = $this->formatArrayValue(
        $pool['gateway_list'] ?? []
      );

      $lines[] = implode("\n", [
        "{$number}. " . ($pool['name'] ?: '-'),
        '   Site: ' . ($pool['site'] ?: '-'),
        '   Network: ' . ($pool['network'] ?: '-'),
        '   Netmask: ' . ($pool['netmask'] ?: '-'),
        '   Gateway: ' . $gateways,
        '   DNS: ' . $dns,
        '   Forbidden IP: '
        . ($pool['forbidden_ip'] ?: '-'),
        '   Lease: '
        . ($pool['lease_human'] ?: '-'),
        '   VLAN terkait: '
        . $this->formatVlans(
          $pool['vlans'] ?? []
        ),
        '   Remark: '
        . ($pool['remark'] ?: '-'),
      ]);
    }

    if (count($pools) >= 20) {
      $lines[] = '';
      $lines[] = 'Hasil dibatasi maksimal 20 data.';
    }

    return implode("\n", $lines);
  }

  protected function formatSingleIpAddress(
    IpAddress $ipAddress
  ): string {
    return implode("\n", [
      'Data IP ditemukan:',
      '',
      "IP: {$ipAddress->ip}",
      'Deskripsi: '
      . ($ipAddress->description ?: '-'),
      'VLAN: '
      . ($ipAddress->vlan?->vlan_id ?? '-')
      . ' - '
      . ($ipAddress->vlan?->name ?: '-'),
      'Network VLAN: '
      . ($ipAddress->vlan?->network ?: '-'),
      'Gateway VLAN: '
      . ($ipAddress->vlan?->gateway ?: '-'),
      'Site: '
      . ($ipAddress->vlan?->site ?: '-'),
      'DHCP Pool: '
      . ($ipAddress->vlan?->dhcpPool?->name ?: '-'),
    ]);
  }

  protected function formatArrayValue(
    mixed $value
  ): string {
    if (!is_array($value) || $value === []) {
      return '-';
    }

    $items = array_filter(
      array_map(
        static fn(mixed $item): string => trim(
          is_scalar($item)
          ? (string) $item
          : json_encode(
            $item,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
          )
        ),
        $value
      )
    );

    return $items !== []
      ? implode(', ', $items)
      : '-';
  }

  protected function formatVlans(array $vlans): string
  {
    if ($vlans === []) {
      return '-';
    }

    $items = array_map(
      static function (array $vlan): string {
        $vlanId = $vlan['vlan_id'] ?? '-';
        $name = $vlan['name'] ?? '-';

        return "VLAN {$vlanId} ({$name})";
      },
      $vlans
    );

    return implode(', ', $items);
  }
}
