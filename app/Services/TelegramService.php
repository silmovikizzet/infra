<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
  protected string $token;

  public function __construct()
  {
    $this->token = config('services.telegram.bot_token');
  }

  public function getUpdates(?int $offset = null): array
  {
    $response = Http::timeout(35)->get(
      "https://api.telegram.org/bot{$this->token}/getUpdates",
      [
        'offset' => $offset,
        'timeout' => 30,
      ]
    );

    return $response->json('result', []);
  }

  public function sendMessage(int|string $chatId, string $text): void
  {
    Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
      'chat_id' => $chatId,
      'text' => $text,
    ]);
  }
}
