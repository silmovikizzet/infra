<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaService
{
  public function chat(string $message): string
  {
    $response = Http::timeout(120)->post(
      config('services.ollama.url') . '/api/chat',
      [
        'model' => config('services.ollama.model'),
        'stream' => false,

        'options' => [
          'num_thread' => 2,
          'num_predict' => 150,
          'temperature' => 0.2,
        ],

        'messages' => [
          [
            'role' => 'system',
            'content' => implode("\n", [
              'Kamu adalah AI Assistant internal.',
              'Selalu jawab dalam Bahasa Indonesia.',
              'Jawab ringkas dan jangan mengarang.',
            ]),
          ],
          [
            'role' => 'user',
            'content' => $message,
          ],
        ],
      ]
    );

    return $response->json('message.content') ?? 'Maaf, AI tidak memberi respons.';
  }
}
