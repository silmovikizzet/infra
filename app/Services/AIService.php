<?php

namespace App\Services;

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
      'invoice' => $this->ollama->chat(
        "User bertanya soal invoice/tagihan. Saat ini integrasi database belum diaktifkan. Jawab bahwa fitur invoice sedang disiapkan."
      ),

      'router_offline' => $this->ollama->chat(
        "User bertanya router offline. Saat ini integrasi database belum diaktifkan. Jawab bahwa fitur cek router sedang disiapkan."
      ),

      default => $this->ollama->chat($text),
    };
  }
}
