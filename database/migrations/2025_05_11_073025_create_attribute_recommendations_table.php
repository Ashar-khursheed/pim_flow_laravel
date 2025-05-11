<?php

// database/migrations/xxxx_xx_xx_create_attribute_recommendations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttributeRecommendationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->string('family_name')->nullable();
            $table->text('common_attributes')->nullable();
            $table->text('variants')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_recommendations');
    }
}
