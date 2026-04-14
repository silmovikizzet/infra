<?php

// database/migrations/xxxx_xx_xx_create_vlans_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('vlans', function (Blueprint $table) {
      $table->id();
      $table->integer('vlan_id');
      $table->string('name');
      $table->string('network');
      $table->string('gateway');
      $table->integer('client')->nullable();
      $table->string('start_ip')->nullable();
      $table->string('last_ip')->nullable();
      $table->string('remark')->nullable();
      $table->string('site')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('vlans');
  }
};
