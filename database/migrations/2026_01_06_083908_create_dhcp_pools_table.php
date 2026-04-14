<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('dhcp_pools', function (Blueprint $table) {
      $table->id();

      // optional kalau mau multi-tenant per site (biar pool bisa dibatasi per site)
      $table->string('site')->nullable()->index();

      $table->string('name', 100)->index(); // contoh: AP-MHG

      // network CIDR + netmask (biar gampang query & display)
      $table->string('network', 50);        // contoh: 10.2.86.0/23 (disarankan)
      $table->string('netmask', 50)->nullable(); // contoh: 255.255.254.0

      // list IP: simpan JSON biar bisa banyak
      $table->json('dns_list')->nullable();      // ["10.2.0.251","172.16.0.252"]
      $table->json('gateway_list')->nullable();  // ["10.2.87.254"]

      // single IP
      $table->ipAddress('forbidden_ip')->nullable(); // 10.2.87.254

      // expired: simpan detik + breakdown (pilih salah satu cara)
      // cara simple: total seconds
      $table->unsignedInteger('lease_seconds')->default(0); // 0=never/disable or follow device policy

      $table->text('remark')->nullable();

      // kalau mau soft delete
      // $table->softDeletes();

      $table->timestamps();

      // unique per site+name biar gak dobel (kalau site dipakai)
      $table->unique(['site', 'name']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('dhcp_pools');
  }
};
