<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    // rename table assets -> asset_switch
    if (Schema::hasTable('assets') && !Schema::hasTable('asset_switch')) {
      Schema::rename('assets', 'asset_switch');
    }
  }

  public function down(): void
  {
    // rollback: asset_switch -> assets
    if (Schema::hasTable('asset_switch') && !Schema::hasTable('assets')) {
      Schema::rename('asset_switch', 'assets');
    }
  }
};
