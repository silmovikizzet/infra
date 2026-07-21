<?php

namespace App\Services;

use JsonException;

class AIService
{
  public function __construct(
    protected OllamaService $ollama,
    protected ToolService $tools,
  ) {
  }

  /**
   * @throws JsonException
   */
  public function handleMessage(string $text): string
  {
    $intent = $this->tools->detectIntent($text);

    if ($intent === 'ip_address') {
      $ipAddresses = $this->tools->getIpAddresses(
        limit: 20
      );

      if ($ipAddresses === []) {
        return 'Tidak ada data IP address yang ditemukan.';
      }

      $databaseContext = json_encode(
        $ipAddresses,
        JSON_THROW_ON_ERROR
        | JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
      );

      return $this->ollama->chat(
        <<<PROMPT
Pertanyaan pengguna:
{$text}

Berikut adalah data IP address dari database MySQL:
{$databaseContext}

Aturan jawaban:
- Jawab hanya berdasarkan data database tersebut.
- Jangan menambahkan IP address yang tidak ada.
- Jangan mengarang nama VLAN, site, atau deskripsi.
- Jika informasi yang ditanyakan tidak tersedia, katakan bahwa datanya tidak tersedia.
- Gunakan Bahasa Indonesia.
- Tampilkan IP address dalam format yang mudah dibaca.
PROMPT
      );
    }

    if ($intent === 'invoice') {
      return 'Fitur pembacaan invoice belum diaktifkan.';
    }

    return $this->ollama->chat($text);
  }
}
