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

    if ($intent === 'ip_address') {
      return $this->handleIpAddressQuestion($text);
    }

    /*
     * Hanya pertanyaan umum yang diteruskan ke Ollama.
     */
    return $this->ollama->chat($text);
  }

  protected function handleIpAddressQuestion(string $text): string
  {
    $exactIp = $this->tools->extractIpAddress($text);

    /*
     * Jika user menyebut IP lengkap, query langsung satu baris.
     */
    if ($exactIp !== null) {
      $ipAddress = $this->tools->findIpAddress($exactIp);

      if (!$ipAddress) {
        return "IP {$exactIp} tidak ditemukan di database.";
      }

      return $this->formatSingleIpAddress($ipAddress);
    }

    /*
     * Jika tidak menyebut IP lengkap, coba cari berdasarkan
     * deskripsi atau site.
     */
    $keyword = $this->tools->extractSearchKeyword($text);

    $ipAddresses = $this->tools->getIpAddresses(
      keyword: $keyword,
      limit: 20
    );

    if ($ipAddresses === []) {
      return $keyword !== null
        ? "Tidak ada IP address yang cocok dengan pencarian \"{$keyword}\"."
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
        '   Deskripsi: ' . ($ipAddress['description'] ?: '-'),
        '   Site: ' . ($ipAddress['site'] ?: '-'),
        '   VLAN ID: ' . ($ipAddress['vlan_id'] ?: '-'),
      ]);
    }

    if (count($ipAddresses) >= 20) {
      $lines[] = '';
      $lines[] = 'Hasil dibatasi maksimal 20 data.';
    }

    return implode("\n", $lines);
  }

  protected function formatSingleIpAddress(IpAddress $ipAddress): string
  {
    return implode("\n", [
      'Data IP ditemukan:',
      '',
      "IP: {$ipAddress->ip}",
      'Deskripsi: ' . ($ipAddress->description ?: '-'),
      'Site: ' . ($ipAddress->vlan?->site ?: '-'),
      'VLAN ID: ' . ($ipAddress->vlan_id ?: '-'),
    ]);
  }
}
