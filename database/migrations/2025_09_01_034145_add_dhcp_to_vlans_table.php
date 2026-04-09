<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('vlans', function (Blueprint $table) {
            $table->string('dhcp')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('vlans', function (Blueprint $table) {
            $table->boolean('dhcp')->default(false)->change();
        });
    }
};
