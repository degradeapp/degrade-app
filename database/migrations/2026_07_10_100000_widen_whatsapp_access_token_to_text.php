<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O access_token é gravado criptografado (Crypt::encryptString) e o payload
     * sempre passa de 255 caracteres. Em SQLite o varchar(255) não é imposto e
     * ninguém percebeu; em PostgreSQL o INSERT estoura (22001). Coluna vira text.
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->text('access_token')->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('access_token')->change();
        });
    }
};
