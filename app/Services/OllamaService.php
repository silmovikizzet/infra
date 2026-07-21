<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaService
{
  public function chat(string $message): string
  {
    $response = Http::timeout(120)->post(config('services.ollama.url') . '/api/chat', [
      'model' => config('services.ollama.model'),
      'stream' => false,
      'messages' => [
        [
          'role' => 'system',
          'content' => '
Kamu adalah AI Assistant internal.
Selalu jawab dalam Bahasa Indonesia.
Jawab singkat, jelas, dan jangan mengarang.
Jika tidak tahu, katakan tidak tahu.
',
        ],
        [
          'role' => 'user',
          'content' => $message,
        ],
      ],
    ]);

    return $response->json('message.content') ?? 'Maaf, AI tidak memberi respons.';
  }
}
