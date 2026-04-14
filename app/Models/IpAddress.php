<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class IpAddress extends Model
{
  use HasFactory;

  protected $table = 'ip_addresses';

  protected $fillable = [
    'ip',
    'description',
    'vlan_id',
  ];

  /**
   * IpAddress belongs to a Vlan.
   */
  public function vlan(): BelongsTo
  {
    return $this->belongsTo(Vlan::class);
  }

  public function scopeOwnedByUser(Builder $query, User $user): Builder
  {
    $sites = $user->sites()
      ->pluck('site')
      ->map(fn($s) => trim((string) $s))
      ->filter()
      ->unique()
      ->values()
      ->all();

    if (empty($sites)) {
      return $query->whereRaw('1 = 0');
    }

    return $query->whereHas('vlan', function (Builder $q) use ($sites) {
      $q->whereIn('site', $sites);
    });
  }
}
