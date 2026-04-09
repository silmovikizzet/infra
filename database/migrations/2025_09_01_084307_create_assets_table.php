<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // 1️⃣ Tabel credentials dulu
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');      // nama credential
            $table->string('username');  // username
            $table->string('type');  // type
            $table->text('password');    // password (hashed)
            $table->integer('port');
            $table->timestamps();
        });

        // 2️⃣ Tabel assets
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('hostname');
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->string('group')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('end_of_support')->nullable();  // date nullable
            $table->string('warranty')->nullable();
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

    public function down()
    {
        Schema::dropIfExists('assets');
        Schema::dropIfExists('credentials');
    }
};
