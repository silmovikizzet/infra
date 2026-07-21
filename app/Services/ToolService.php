<?php

namespace App\Services;

use App\Models\IpAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ToolService
{
  public function detectIntent(string $text): string
  {
    $text = mb_strtolower(trim($text));

    if (
      str_contains($text, 'ip address')
      || str_contains($text, 'alamat ip')
      || str_contains($text, 'daftar ip')
      || str_contains($text, 'ip yang tersedia')
      || str_contains($text, 'ip tersedia')
      || str_contains($text, 'ip digunakan')
    ) {
      return 'ip_address';
    }

    if (
      str_contains($text, 'invoice')
      || str_contains($text, 'tagihan')
    ) {
      return 'invoice';
    }

    return 'general_chat';
  }

  public function getIpAddresses(
    ?User $user = null,
    int $limit = 20
  ): array {
    $query = IpAddress::query()
      ->with([
        'vlan',
      ]);

    /*
     * Jika user diberikan, batasi IP hanya berdasarkan site
     * yang dimiliki user melalui scope ownedByUser().
     */
    if ($user !== null) {
      $query->ownedByUser($user);
    }

    return $query
      ->orderBy('ip')
      ->limit($limit)
      ->get()
      ->map(function (IpAddress $ipAddress): array {
        return [
          'id' => $ipAddress->getKey(),
          'ip' => $ipAddress->ip,
          'description' => $ipAddress->description,
          'vlan_id' => $ipAddress->vlan_id,
          'vlan' => $ipAddress->vlan?->name,
          'site' => $ipAddress->vlan?->site,
          'created_at' => $ipAddress->created_at?->format(
            'Y-m-d H:i:s'
          ),
        ];
      })
      ->values()
      ->all();
  }

  public function searchIpAddresses(
    string $keyword,
    ?User $user = null,
    int $limit = 20
  ): array {
    $keyword = trim($keyword);

    $query = IpAddress::query()
      ->with([
        'vlan',
      ]);

    if ($user !== null) {
      $query->ownedByUser($user);
    }

    if ($keyword !== '') {
      $query->where(function (Builder $query) use ($keyword): void {
        $query
          ->where('ip', 'like', '%' . $keyword . '%')
          ->orWhere(
            'description',
            'like',
            '%' . $keyword . '%'
          )
          ->orWhereHas(
            'vlan',
            function (Builder $vlanQuery) use ($keyword): void {
              $vlanQuery
                ->where(
                  'site',
                  'like',
                  '%' . $keyword . '%'
                )
                ->orWhere(
                  'name',
                  'like',
                  '%' . $keyword . '%'
                );
            }
          );
      });
    }

    return $query
      ->orderBy('ip')
      ->limit($limit)
      ->get()
      ->map(function (IpAddress $ipAddress): array {
        return [
          'id' => $ipAddress->getKey(),
          'ip' => $ipAddress->ip,
          'description' => $ipAddress->description,
          'vlan_id' => $ipAddress->vlan_id,
          'vlan' => $ipAddress->vlan?->name,
          'site' => $ipAddress->vlan?->site,
        ];
      })
      ->values()
      ->all();
  }
}
