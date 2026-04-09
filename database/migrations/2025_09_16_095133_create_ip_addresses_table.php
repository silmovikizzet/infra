<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();

            $table->string('ip')->unique();
            $table->string('description')->unique();
            $table->timestamps();

            $table->foreignId('vlan_id')
                ->constrained('vlans') // otomatis ke vlans.id
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
    }
};
