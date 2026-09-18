<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->enum('entity_type', ['individual', 'retail_shop', 'wholesale', 'company'])->default('individual')->after('vendor_type');
            $table->enum('verification_tier', ['basic', 'verified'])->default('basic')->after('entity_type');
            $table->string('national_id_number', 20)->nullable()->after('verification_tier');
            $table->boolean('is_vat_registered')->default(false)->after('national_id_number');
            $table->string('tax_registration_number', 30)->nullable()->after('is_vat_registered');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropColumn(['entity_type', 'verification_tier', 'national_id_number', 'is_vat_registered', 'tax_registration_number']);
        });
    }
};