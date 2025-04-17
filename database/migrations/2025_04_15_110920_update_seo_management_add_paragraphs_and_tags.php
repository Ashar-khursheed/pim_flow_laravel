<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->text('paragraph_1')->nullable()->after('id'); // Add after appropriate column if needed
            $table->text('paragraph_2')->nullable();
            $table->text('paragraph_3')->nullable();
            $table->text('paragraph_4')->nullable();

            $table->longtext('popular_tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->dropColumn([
                'paragraph_1',
                'paragraph_2',
                'paragraph_3',
                'paragraph_4',
                'popular_tags'
            ]);
        });
    }
};
