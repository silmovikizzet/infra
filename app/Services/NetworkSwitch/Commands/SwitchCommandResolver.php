<?php

namespace App\Services\NetworkSwitch\Commands;

use App\Models\AssetSwitch;
use App\Services\NetworkSwitch\Parsers\HpeFlexFabric5940InterfaceParser;

final class SwitchCommandResolver
{
  /**
   * Resolve command dan parser berdasarkan tipe/model switch.
   * Tambahkan switch type berikutnya di method ini tanpa mengubah Livewire.
   */
  public function resolveInterfaceProfile(AssetSwitch $switch): array
  {
    if (HpeFlexFabric5940Commands::supports($switch)) {
      return [
        'key' => HpeFlexFabric5940Commands::KEY,
        'label' => HpeFlexFabric5940Commands::LABEL,
        'command' => HpeFlexFabric5940Commands::interfaceBrief(),
        'disable_paging' => HpeFlexFabric5940Commands::disablePaging(),
        'parser' => HpeFlexFabric5940InterfaceParser::class,
      ];
    }

    throw new \RuntimeException(
      'Tipe switch belum didukung. Saat ini baru tersedia profile HPE FlexFabric 5940.'
    );
  }
}
