<?php

namespace App\Services\NetworkSwitch\Commands;

use App\Models\AssetSwitch;

final class HpeFlexFabric5940Commands
{
  public const KEY = 'hpe_ff_5940';

  public const LABEL = 'HPE FlexFabric 5940';

  /**
   * Command read-only untuk menampilkan ringkasan semua interface.
   */
  public static function interfaceBrief(): string
  {
    return 'display interface brief';
  }

  /**
   * Menonaktifkan pagination untuk sesi CLI Comware.
   */
  public static function disablePaging(): array
  {
    return [
      'screen-length disable',
    ];
  }

  /**
   * Deteksi profile berdasarkan kolom identitas yang umum dipakai AssetSwitch.
   * Nilai seperti "HPE FF 5940", "HPE FlexFabric 5940", atau "5940" didukung.
   */
  public static function supports(AssetSwitch $switch): bool
  {
    $identity = strtolower(implode(' ', array_filter([
      self::scalarAttribute($switch, 'vendor'),
      self::scalarAttribute($switch, 'brand'),
      self::scalarAttribute($switch, 'manufacturer'),
      self::scalarAttribute($switch, 'model'),
      self::scalarAttribute($switch, 'type'),
      self::scalarAttribute($switch, 'device_type'),
    ])));

    return str_contains($identity, '5940');
  }

  private static function scalarAttribute(AssetSwitch $switch, string $attribute): string
  {
    $value = $switch->getAttribute($attribute);

    return is_scalar($value) ? trim((string) $value) : '';
  }
}
