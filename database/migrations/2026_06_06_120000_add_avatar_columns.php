<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
        });

        Schema::table('barbers', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('phone');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
        Schema::table('barbers', fn (Blueprint $table) => $table->dropColumn('photo_path'));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('logo_path'));
    }
};
