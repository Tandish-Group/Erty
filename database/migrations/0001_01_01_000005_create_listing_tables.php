<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Listings.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('listings')) {
            Schema::create('listings', function (Blueprint $table) {
                $table->id();
                $table->text('name');
                $table->string('ref');
                $table->text('slug');
                $table->text('category');
                $table->text('county')->nullable();
                $table->text('county_specific')->nullable();
                $table->string('longitude')->nullable();
                $table->string('latitude')->nullable();
                $table->text('location_description')->nullable();
                $table->text('type');
                $table->text('class')->nullable();
                $table->text('furnishing')->nullable();
                $table->string('bedrooms')->nullable();
                $table->integer('bathrooms')->nullable();
                $table->integer('sq_area')->nullable();
                $table->integer('amount')->nullable();
                $table->integer('viewing_fee')->nullable();
                $table->text('property_description')->nullable();
                $table->text('status')->default('Draft');
                $table->text('availability')->nullable();
                $table->bigInteger('complex_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->text('link')->nullable();
                $table->string('currency')->default('KES');
                $table->string('condition')->default('average');
                $table->bigInteger('original_user_id')->nullable();
                $table->bigInteger('commercial_unit_id')->nullable();
                $table->integer('units')->default(1);
                $table->timestamp('reviewed_at')->nullable();
                $table->bigInteger('reviewed_by')->nullable();
                $table->bigInteger('complex_unit_id')->nullable();
                $table->integer('deposit')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->string('offer', 8)->default('rent');
                $table->decimal('sale_price', 15, 2)->nullable();
                $table->unique(['commercial_unit_id'], 'listings_commercial_unit_id_unique');
                $table->unique(['complex_unit_id'], 'listings_complex_unit_id_unique');
                $table->unique(['ref'], 'listings_ref_unique');
                $table->index(['status', 'user_id'], 'listings_status_user_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('listings');
        Schema::enableForeignKeyConstraints();
    }
};
