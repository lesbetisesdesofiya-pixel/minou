<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Paie livreur : snapshot des frais de livraison au moment de l'affectation
            $table->decimal('driver_amount', 10, 2)->default(0)->after('delivery_fee');
            $table->boolean('driver_paid')->default(false)->after('driver_amount');
        });

        Schema::table('admins', function (Blueprint $table) {
            // Compte livreur (rôle DELIVERY) lié à un livreur
            $table->foreignId('delivery_person_id')->nullable()->after('role')
                ->constrained('delivery_persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['driver_amount', 'driver_paid']);
        });
        Schema::table('admins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_person_id');
        });
    }
};
