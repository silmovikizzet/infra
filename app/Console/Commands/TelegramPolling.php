<?php

namespace App\Console\Commands;

use App\Services\AIService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramPolling extends Command
{
  protected $signature = 'telegram:poll';

  protected $description = 'Menjalankan long polling Telegram Bot';

  public function handle(
    TelegramService $telegram,
    AIService $ai
  ): int {
    /*
     * Ganti dengan username bot Telegram kamu.
     *
     * Contoh:
     * @mhg_ai_bot
     *
     * Harus memakai karakter @.
     */
    $botUsername = '@MHNSNotification_bot';

    $this->info('Telegram polling started...');
    $this->info("Bot username: {$botUsername}");
    $this->info('Tekan CTRL+C untuk menghentikan polling.');

    while (true) {
      try {
        $offset = Cache::get('telegram_update_offset');

        $updates = $telegram->getUpdates(
          is_numeric($offset) ? (int) $offset : null
        );

        foreach ($updates as $update) {
          $updateId = $update['update_id'] ?? null;

          /*
           * Simpan offset agar update yang sama tidak diproses ulang.
           */
          if ($updateId !== null) {
            Cache::forever(
              'telegram_update_offset',
              ((int) $updateId) + 1
            );
          }

          /*
           * Telegram bisa mengirim update selain message,
           * misalnya edited_message, callback_query, dan lainnya.
           *
           * Untuk tahap ini hanya proses message biasa.
           */
          $message = $update['message'] ?? null;

          if (!is_array($message)) {
            continue;
          }

          /*
           * Abaikan pesan yang dikirim oleh bot lain.
           */
          if (($message['from']['is_bot'] ?? false) === true) {
            continue;
          }

          $chatId = $message['chat']['id'] ?? null;
          $chatType = $message['chat']['type'] ?? null;
          $messageId = $message['message_id'] ?? null;
          $text = trim((string) ($message['text'] ?? ''));

          /*
           * Untuk sementara hanya memproses pesan teks.
           */
          if ($chatId === null || $text === '') {
            continue;
          }

          $isPrivateChat = $chatType === 'private';

          $isGroupChat = in_array(
            $chatType,
            ['group', 'supergroup'],
            true
          );

          /*
           * Cek apakah username bot disebut di dalam pesan.
           */
          $isMentioned = str_contains(
            mb_strtolower($text),
            mb_strtolower($botUsername)
          );

          /*
           * Cek apakah pesan merupakan reply ke pesan bot.
           */
          $replyToMessage = $message['reply_to_message'] ?? null;

          $isReplyToBot = is_array($replyToMessage)
            && (($replyToMessage['from']['is_bot'] ?? false) === true);

          /*
           * Chat pribadi selalu diproses.
           *
           * Di grup, hanya proses jika:
           * - bot di-mention; atau
           * - user reply ke pesan bot.
           */
          if (
            !$isPrivateChat
            && $isGroupChat
            && !$isMentioned
            && !$isReplyToBot
          ) {
            continue;
          }

          /*
           * Abaikan jenis chat yang tidak dikenali.
           */
          if (!$isPrivateChat && !$isGroupChat) {
            continue;
          }

          /*
           * Hapus mention bot dari pertanyaan user.
           *
           * Contoh:
           * "@mhg_ai halo" menjadi "halo".
           */
          if ($isMentioned) {
            $text = trim(
              str_ireplace(
                $botUsername,
                '',
                $text
              )
            );
          }

          /*
           * Jika user hanya mengirim mention tanpa pertanyaan,
           * anggap sebagai sapaan.
           */
          if ($text === '') {
            $text = 'Halo';
          }

          $senderId = $message['from']['id'] ?? null;
          $senderUsername = $message['from']['username'] ?? null;
          $senderFirstName = $message['from']['first_name'] ?? null;
          $senderLastName = $message['from']['last_name'] ?? null;

          $senderName = trim(
            implode(' ', array_filter([
              $senderFirstName,
              $senderLastName,
            ]))
          );

          Log::info('Telegram message received.', [
            'update_id' => $updateId,
            'message_id' => $messageId,
            'chat_id' => $chatId,
            'chat_type' => $chatType,
            'sender_id' => $senderId,
            'sender_username' => $senderUsername,
            'sender_name' => $senderName,
            'is_mentioned' => $isMentioned,
            'is_reply_to_bot' => $isReplyToBot,
            'text' => $text,
          ]);

          try {
            $reply = $ai->handleMessage($text);

            $reply = trim($reply);

            if ($reply === '') {
              $reply = 'Maaf, AI tidak memberikan jawaban.';
            }

            $telegram->sendMessage(
              $chatId,
              $reply,
              $messageId
            );

            Log::info('Telegram reply sent.', [
              'chat_id' => $chatId,
              'reply_to_message_id' => $messageId,
            ]);
          } catch (Throwable $e) {
            Log::error('Failed to process Telegram message.', [
              'chat_id' => $chatId,
              'message_id' => $messageId,
              'error' => $e->getMessage(),
              'exception' => $e::class,
            ]);

            /*
             * Tetap coba kirim pesan error ke Telegram.
             */
            try {
              $telegram->sendMessage(
                $chatId,
                'Maaf, terjadi kesalahan saat memproses pertanyaan.',
                $messageId
              );
            } catch (Throwable $sendException) {
              Log::error('Failed to send Telegram error message.', [
                'chat_id' => $chatId,
                'error' => $sendException->getMessage(),
                'exception' => $sendException::class,
              ]);
            }
          }
        }
      } catch (Throwable $e) {
        Log::error('Telegram polling error.', [
          'error' => $e->getMessage(),
          'exception' => $e::class,
        ]);

        $this->error(
          now()->format('Y-m-d H:i:s')
          . ' - '
          . $e->getMessage()
        );

        /*
         * Tunggu sebelum mencoba polling ulang agar tidak spam
         * Telegram API atau log ketika terjadi gangguan jaringan.
         */
        sleep(5);
      }
    }

    return self::SUCCESS;
  }
}
