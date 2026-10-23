<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    protected string $index = 'wp_import_images_url_hash_index';

    public function up(): void
    {
        if (! Schema::hasTable('wordpress_import_images')) {
            return;
        }

        // Lets RemoteImageDownloader confirm a reused copy still exists by primary
        // key instead of scanning the unindexed media_files.url column.
        if (! Schema::hasColumn('wordpress_import_images', 'media_file_id')) {
            Schema::table('wordpress_import_images', function (Blueprint $table) {
                $table->unsignedBigInteger('media_file_id')->nullable()->after('local_url');
            });
        }

        // Reuse lookups go by url_hash alone; the (import_id, url_hash) unique key can't serve them.
        if (! $this->indexExists()) {
            Schema::table('wordpress_import_images', function (Blueprint $table) {
                $table->index('url_hash', $this->index);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wordpress_import_images')) {
            return;
        }

        if ($this->indexExists()) {
            Schema::table('wordpress_import_images', function (Blueprint $table) {
                $table->dropIndex($this->index);
            });
        }

        if (Schema::hasColumn('wordpress_import_images', 'media_file_id')) {
            Schema::table('wordpress_import_images', function (Blueprint $table) {
                $table->dropColumn('media_file_id');
            });
        }
    }

    /**
     * Schema::hasIndex() needs Laravel 11+; fall back to Doctrine for older cores.
     */
    protected function indexExists(): bool
    {
        $schema = Schema::getFacadeRoot();

        if (method_exists($schema, 'hasIndex')) {
            return $schema->hasIndex('wordpress_import_images', $this->index);
        }

        try {
            return array_key_exists($this->index, $schema->getConnection()
                ->getDoctrineSchemaManager()
                ->listTableIndexes($schema->getConnection()->getTablePrefix() . 'wordpress_import_images'));
        } catch (Throwable) {
            return false;
        }
    }
};
