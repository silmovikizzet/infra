<?php

namespace App\Services\NetworkSwitch\Parsers;

final class HpeFlexFabric5940InterfaceParser
{
  /**
   * Parse output "display interface brief" Comware 7.
   *
   * Status aplikasi:
   * - UP   => up       (Nyala)
   * - DOWN => down     (Mati)
   * - ADM  => disabled (Administratively down)
   * - Stby => down     (Standby/tidak aktif)
   */
  public function parse(string $output): array
  {
    $interfaces = [];
    $mode = null;
    $lines = preg_split('/\R/', $output) ?: [];

    foreach ($lines as $rawLine) {
      $line = trim($rawLine);

      if ($line === '') {
        continue;
      }

      if (stripos($line, 'under route mode') !== false) {
        $mode = 'route';
        continue;
      }

      if (stripos($line, 'under bridge mode') !== false) {
        $mode = 'bridge';
        continue;
      }

      if ($this->isHeadingOrLegend($line)) {
        continue;
      }

      $parsed = match ($mode) {
        'route' => $this->parseRouteModeLine($line),
        'bridge' => $this->parseBridgeModeLine($line),
        default => $this->parseBridgeModeLine($line)
        ?? $this->parseRouteModeLine($line),
      };

      if ($parsed === null || !$this->isPhysicalInterface($parsed['name'])) {
        continue;
      }

      $interfaces[$parsed['name']] = $parsed;
    }

    $interfaces = array_values($interfaces);

    usort(
      $interfaces,
      fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name'])
    );

    return $interfaces;
  }

  private function parseBridgeModeLine(string $line): ?array
  {
    /*
     * Interface  Link  Speed  Duplex  Type  PVID  Description
     * XGE1/0/1   UP    10G(a) F(a)    T     10    Uplink
     */
    if (
      preg_match(
        '/^(\S+)\s+(UP|DOWN|ADM|Stby)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)(?:\s+(.*))?$/i',
        $line,
        $matches
      ) !== 1
    ) {
      return null;
    }

    return $this->makeResult(
      name: $matches[1],
      rawStatus: $matches[2],
      mode: 'bridge',
      speed: $matches[3],
      duplex: $matches[4],
      linkType: $matches[5],
      pvid: $matches[6],
      protocol: '-',
      mainIp: '-',
      description: $matches[7] ?? ''
    );
  }

  private function parseRouteModeLine(string $line): ?array
  {
    /*
     * Interface  Link  Protocol  Main IP       Description
     * XGE1/0/1   UP    UP        10.10.10.1    Routed uplink
     */
    if (
      preg_match(
        '/^(\S+)\s+(UP|DOWN|ADM|Stby)\s+(\S+)\s+(\S+)(?:\s+(.*))?$/i',
        $line,
        $matches
      ) !== 1
    ) {
      return null;
    }

    return $this->makeResult(
      name: $matches[1],
      rawStatus: $matches[2],
      mode: 'route',
      speed: '-',
      duplex: '-',
      linkType: '-',
      pvid: '-',
      protocol: $matches[3],
      mainIp: $matches[4],
      description: $matches[5] ?? ''
    );
  }

  private function makeResult(
    string $name,
    string $rawStatus,
    string $mode,
    string $speed,
    string $duplex,
    string $linkType,
    string $pvid,
    string $protocol,
    string $mainIp,
    string $description
  ): array {
    $rawStatus = strtoupper(trim($rawStatus));

    return [
      'name' => trim($name),
      'status' => $this->normalizeStatus($rawStatus),
      'raw_status' => $rawStatus,
      'mode' => $mode,
      'speed' => $this->cleanValue($speed),
      'duplex' => $this->cleanValue($duplex),
      'link_type' => $this->cleanValue($linkType),
      'pvid' => $this->cleanValue($pvid),
      'protocol' => $this->cleanValue($protocol),
      'main_ip' => $this->cleanValue($mainIp),
      'description' => $this->cleanValue($description),
    ];
  }

  private function normalizeStatus(string $status): string
  {
    return match ($status) {
      'UP' => 'up',
      'ADM' => 'disabled',
      default => 'down',
    };
  }

  private function isPhysicalInterface(string $name): bool
  {
    return preg_match(
      '/^(?:'
      . 'GE|XGE|FGE|HGE|MGE|'
      . 'GigabitEthernet|Ten-GigabitEthernet|'
      . 'Twenty-FiveGigE|FortyGigE|FiftyGigE|HundredGigE'
      . ')\d+(?:\/\d+){1,3}(?::\d+)?$/i',
      $name
    ) === 1;
  }

  private function isHeadingOrLegend(string $line): bool
  {
    return preg_match(
      '/^(?:Brief information|Link:|Protocol:|Speed or Duplex:|Type:|Interface\s+Link|[-=]{3,})/i',
      $line
    ) === 1;
  }

  private function cleanValue(?string $value): string
  {
    $value = trim((string) $value);

    return $value !== '' ? $value : '-';
  }
}
