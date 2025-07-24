<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('imported_data', function (Blueprint $table) {
            $table->id();
            $table->string('content')->nullable();
            $table->json('data');
            $table->text('taxonomy_terms')->nullable();
            $table->string('title')->nullable();
            $table->string('route_url')->nullable();
            $table->boolean('status')->nullable();
            $table->string('sites')->nullable();
            $table->string('locale')->nullable();
            $table->date('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imported_data');
    }
};
