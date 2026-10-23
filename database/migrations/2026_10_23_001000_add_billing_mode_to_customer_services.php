<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode tagihan per layanan: prabayar (tagih bulan berjalan, bayar di muka) atau
 * pascabayar (tagih pemakaian bulan sebelumnya). Layanan lama = prabayar (perilaku sebelumnya).
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_services', 'billing_mode')) {
            Schema::table('customer_services', function (Blueprint $table) {
                $table->string('billing_mode', 10)->default('prepaid')->after('due_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_services', 'billing_mode')) {
            Schema::table('customer_services', fn (Blueprint $t) => $t->dropColumn('billing_mode'));
        }
    }
};
