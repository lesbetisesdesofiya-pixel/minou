<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\DeliveryPerson;
use App\Models\DishVariation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Admins
        Admin::firstOrCreate(
            ['email' => 'admin@opera.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'RESTAURANT'
            ]
        );

        Admin::firstOrCreate(
            ['email' => 'admin2@opera.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'DELIVERY'
            ]
        );

        // Seed Delivery Persons
        DeliveryPerson::firstOrCreate(
            ['email' => 'amadou@opera.com'],
            [
                'last_name' => 'Coulibaly',
                'first_name' => 'Amadou',
                'phone' => '+225 07 01 02 03 04',
                'active' => true,
                'suspended' => false
            ]
        );

        DeliveryPerson::firstOrCreate(
            ['email' => 'jean@opera.com'],
            [
                'last_name' => 'Koffi',
                'first_name' => 'Jean',
                'phone' => '+225 07 05 06 07 08',
                'active' => true,
                'suspended' => false
            ]
        );

        // Set initial stock of fish variations to 50.0 kg for testing purposes
        DishVariation::whereHas('dish.category', function ($q) {
            $q->where('nom', 'Poissons');
        })->update(['stock_kg' => 50.00]);
    }
}

