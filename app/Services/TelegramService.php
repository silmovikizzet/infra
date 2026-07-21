<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramService
{
  protected string $token;

  protected string $baseUrl;

  public function __construct()
  {
    $token = config('services.telegram.bot_token');

    if (!is_string($token) || trim($token) === '') {
      throw new RuntimeException(
        'TELEGRAM_BOT_TOKEN belum diatur atau konfigurasi Laravel masih tercache.'
      );
    }

    $this->token = trim($token);
    $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
  }

  protected function http(): PendingRequest
  {
    return Http::acceptJson()
      ->asJson()
      ->connectTimeout(10);
  }

  public function getUpdates(?int $offset = null): array
  {
    $parameters = [
      'timeout' => 30,
      'allowed_updates' => [
        'message',
      ],
    ];

    if ($offset !== null) {
      $parameters['offset'] = $offset;
    }

    $response = $this->http()
      ->timeout(40)
      ->post(
        "{$this->baseUrl}/getUpdates",
        $parameters
      )
      ->throw();

    if ($response->json('ok') !== true) {
      throw new RuntimeException(
        (string) (
          $response->json('description')
          ?? 'Telegram getUpdates gagal.'
        )
      );
    }

    $result = $response->json('result', []);

    return is_array($result) ? $result : [];
  }

  public function sendMessage(
    int|string $chatId,
    string $text,
    int|string|null $replyToMessageId = null
  ): array {
    $text = trim($text);

    if ($text === '') {
      $text = 'Maaf, tidak ada jawaban yang dapat ditampilkan.';
    }

    /*
     * Telegram membatasi panjang satu pesan.
     * Untuk sementara dipotong agar request tidak gagal.
     */
    if (mb_strlen($text) > 4000) {
      $text = mb_substr($text, 0, 3990)
        . "\n\n...";
    }

    $parameters = [
      'chat_id' => $chatId,
      'text' => $text,
      'disable_web_page_preview' => true,
    ];

    if ($replyToMessageId !== null) {
      $parameters['reply_parameters'] = [
        'message_id' => $replyToMessageId,
        'allow_sending_without_reply' => true,
      ];
    }

    $response = $this->http()
      ->timeout(30)
      ->post(
        "{$this->baseUrl}/sendMessage",
        $parameters
      )
      ->throw();

    if ($response->json('ok') !== true) {
      throw new RuntimeException(
        (string) (
          $response->json('description')
          ?? 'Telegram sendMessage gagal.'
        )
      );
    }

    $result = $response->json('result', []);

    return is_array($result) ? $result : [];
  }
}
