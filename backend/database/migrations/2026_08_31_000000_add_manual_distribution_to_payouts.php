<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table): void {
            $table->unsignedBigInteger('distribution_input_amount')->nullable();
            $table->string('distribution_input_currency', 16)->nullable();
            $table->unsignedBigInteger('token_unit_value_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table): void {
            $table->dropColumn(['distribution_input_amount', 'distribution_input_currency', 'token_unit_value_snapshot']);
        });
    }
};
