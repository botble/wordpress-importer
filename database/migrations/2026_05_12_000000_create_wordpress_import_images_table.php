<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('wordpress_import_images')) {
            return;
        }

        Schema::create('wordpress_import_images', function (Blueprint $table) {
            $table->id();
            $table->string('import_id', 36)->index();
            $table->text('original_url');
            // SHA1 hex of original_url — keeps the unique index size bounded so
            // we can race-safely upsert by (import_id, url_hash).
            $table->char('url_hash', 40);
            $table->text('local_url')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'status']);
            $table->unique(['import_id', 'url_hash'], 'wp_import_images_import_url_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_import_images');
    }
};
