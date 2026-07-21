<?php

namespace App\Services;

class ToolService
{
  public function detectIntent(string $text): string
  {
    $text = strtolower($text);

    if (str_contains($text, 'invoice') || str_contains($text, 'tagihan')) {
      return 'invoice';
    }

    if (str_contains($text, 'router') && str_contains($text, 'offline')) {
      return 'router_offline';
    }

    return 'general_chat';
  }
}
