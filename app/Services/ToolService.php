<?php

namespace App\Services;

use App\Models\IpAddress;
use Illuminate\Database\Eloquent\Builder;

class ToolService
{
  public function detectIntent(string $text): string
  {
    $normalizedText = mb_strtolower(trim($text));

    if (
      str_contains($normalizedText, 'ip address')
      || str_contains($normalizedText, 'alamat ip')
      || str_contains($normalizedText, 'daftar ip')
      || str_contains($normalizedText, 'ip berapa')
      || str_contains($normalizedText, 'ip mana')
      || $this->extractIpAddress($normalizedText) !== null
    ) {
      return 'ip_address';
    }

    return 'general_chat';
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

    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
      ? $ip
      : null;
  }

  public function findIpAddress(string $ip): ?IpAddress
  {
    return IpAddress::query()
      ->with('vlan')
      ->where('ip', $ip)
      ->first();
  }

  public function getIpAddresses(
    ?string $keyword = null,
    int $limit = 20
  ): array {
    $query = IpAddress::query()
      ->with('vlan');

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
              $vlanQuery->where(
                'site',
                'like',
                '%' . $keyword . '%'
              );
            }
          );
      });
    }

    return $query
      ->orderByRaw(
        'INET_ATON(ip) IS NULL, INET_ATON(ip)'
      )
      ->limit(max(1, min($limit, 50)))
      ->get()
      ->map(function (IpAddress $ipAddress): array {
        return [
          'ip' => $ipAddress->ip,
          'description' => $ipAddress->description,
          'site' => $ipAddress->vlan?->site,
          'vlan_id' => $ipAddress->vlan_id,
        ];
      })
      ->values()
      ->all();
  }

  public function extractSearchKeyword(string $text): ?string
  {
    $text = mb_strtolower(trim($text));

    $ignoredWords = [
      'tolong',
      'cari',
      'cek',
      'lihat',
      'tampilkan',
      'daftar',
      'data',
      'alamat',
      'ip',
      'address',
      'yang',
      'di',
      'untuk',
      'berapa',
      'mana',
      'semua',
    ];

    $text = str_replace($ignoredWords, ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);

    $keyword = trim((string) $text);

    return $keyword !== '' ? $keyword : null;
  }
}
