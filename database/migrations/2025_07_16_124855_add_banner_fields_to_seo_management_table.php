<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->string('banner_image_alt_text')->nullable()->after('updated_at');
            $table->string('banner_image_file')->nullable()->after('banner_image_alt_text');
        });
    }

    public function down(): void
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->dropColumn(['banner_image_alt_text', 'banner_image_file']);
        });
    }
};
