<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->string('national_id_front_image')->nullable()->after('national_id_number');
            $table->string('national_id_back_image')->nullable()->after('national_id_front_image');
            $table->string('commercial_register_image')->nullable()->after('commercial_register_no');
            $table->string('address_line1')->nullable()->after('commercial_register_image');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city')->nullable()->after('address_line2');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'national_id_front_image',
                'national_id_back_image',
                'commercial_register_image',
                'address_line1',
                'address_line2',
                'city',
            ]);
        });
    }
};