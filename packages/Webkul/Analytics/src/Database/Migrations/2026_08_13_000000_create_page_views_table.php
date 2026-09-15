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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('path');
            $table->string('referrer_source')->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('visitor_id');
            $table->index(['path', 'created_at']);
            $table->index(['referrer_source', 'created_at']);

            $table->foreign('channel_id')->references('id')->on('channels')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
