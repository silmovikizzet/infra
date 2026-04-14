<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vlans', function (Blueprint $table) {
            $table->foreignId('dhcp_pool_id')
                ->nullable()
                ->after('dhcp') // sesuaikan posisi kolom yg kamu mau
                ->constrained('dhcp_pools')
                ->nullOnDelete(); // kalau pool dihapus, vlan jadi null (karena vlan boleh tanpa dhcp)
        });
    }

    public function down(): void
    {
        Schema::table('vlans', function (Blueprint $table) {
            $table->dropForeign(['dhcp_pool_id']);
            $table->dropColumn('dhcp_pool_id');
        });
    }
};
