<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedBigInteger('tenant_id')->index();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
