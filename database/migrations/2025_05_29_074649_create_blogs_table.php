<?php
// database/migrations/xxxx_xx_xx_create_blogs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('desktop_banner')->nullable();
            $table->string('desktop_banner_alt')->nullable();
            $table->string('mobile_banner')->nullable();
            $table->string('mobile_banner_alt')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('thumbnail_alt')->nullable();
            $table->longtext('description')->nullable(); // Array of paragraphs
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('blog_category_id');            
            $table->longtext('faqs')->nullable(); // Q&A array
            $table->text('tags')->nullable(); // Array of tags
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_likes')->default(0);
            $table->unsignedBigInteger('total_shares')->default(0);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('blogs');
    }
};
