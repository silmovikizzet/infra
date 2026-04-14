<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('asset_access_point', function (Blueprint $table) {
      $table->id();
      $table->string('hostname');
      $table->string(column: 'group')->nullable();
      $table->string('type')->nullable();
      $table->string('ip_address')->nullable();
      $table->string('mac_address')->nullable();
      $table->string('serial_number')->nullable();
      $table->string('warranty')->nullable();
      $table->date('end_of_support')->nullable();  // date nullable
      $table->string('firmware_version')->nullable();
      $table->string('location')->nullable();
      $table->string('floor')->nullable();
      $table->string('tower')->nullable();

      // relation ke credentials
      $table->unsignedBigInteger('credential_id')->nullable();
      $table->foreign('credential_id')
        ->references('id')
        ->on('credentials')
        ->onDelete('set null');

      $table->text('remark')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('asset_access_point');
  }
};
