<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            // چندریختی (polymorphic) چون یک کلیدواژه‌ی هدف می‌تواند به یک Article یا یک Page تعلق داشته باشد
            $table->morphs('keywordable');
            $table->string('keyword');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
