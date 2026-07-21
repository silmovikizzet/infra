<?php

namespace App\Console\Commands;

use App\Services\AIService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TelegramPolling extends Command
{
  protected $signature = 'telegram:poll';

  protected $description = 'Run Telegram bot polling';

  public function handle(
    TelegramService $telegram,
    AIService $ai
  ): int {
    $this->info('Telegram polling started...');

    while (true) {
      try {
        $offset = Cache::get('telegram_update_offset');

        $updates = $telegram->getUpdates($offset);

        foreach ($updates as $update) {
          Cache::put('telegram_update_offset', $update['update_id'] + 1);

          $message = $update['message'] ?? null;

          if (!$message) {
            continue;
          }

          $chatId = $message['chat']['id'] ?? null;
          $text = trim($message['text'] ?? '');

          if (!$chatId || $text === '') {
            continue;
          }

          $reply = $ai->handleMessage($text);

          $telegram->sendMessage($chatId, $reply);
        }
      } catch (Throwable $e) {
        report($e);
        $this->error($e->getMessage());
        sleep(5);
      }
    }

    return self::SUCCESS;
  }
}
