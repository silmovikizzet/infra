<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vlans', function (Blueprint $table) {
            // aman kalau kolomnya memang ada (kalau tidak, migration akan error)
            // kalau kamu yakin kolomnya ada, boleh langsung dropColumn tanpa if.
            if (Schema::hasColumn('vlans', 'client')) {
                $table->dropColumn('client');
            }
            if (Schema::hasColumn('vlans', 'start_ip')) {
                $table->dropColumn('start_ip');
            }
            if (Schema::hasColumn('vlans', 'last_ip')) {
                $table->dropColumn('last_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vlans', function (Blueprint $table) {
            $table->unsignedInteger('client')->nullable()->after('remark');
            $table->ipAddress('start_ip')->nullable()->after('client');
            $table->ipAddress('last_ip')->nullable()->after('start_ip');
        });
    }
};
