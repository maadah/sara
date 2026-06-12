<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default admin user
        User::updateOrCreate(
            ['email' => 'admin@rehla.com'],
            [
                'name' => 'invnty',
                'email' => 'admin@rehla.com',
                'password' => Hash::make('2001587'),
                'role' => 'admin',
                'status' => 'approved',
                'phone' => '0000000000',
            ]
        );

        // Create some default subscription packages
        Subscription::updateOrCreate(
            ['name' => 'الباقة الاساسية'],
            [
                'name' => 'الباقة الاساسية',
                'type' => 'basic',
                'description' => 'باقة اساسية للمتاجر الصغيرة',
                'price' => 99.00,
                'duration_days' => 30,
                'features' => ['دعم فني', 'لوحة تحكم'],
                'is_active' => true,
            ]
        );

        Subscription::updateOrCreate(
            ['name' => 'الباقة المتقدمة'],
            [
                'name' => 'الباقة المتقدمة',
                'type' => 'premium',
                'description' => 'باقة متقدمة للمتاجر الكبيرة',
                'price' => 199.00,
                'duration_days' => 30,
                'features' => ['دعم فني', 'لوحة تحكم', 'تقارير متقدمة', 'دعم اولوية'],
                'is_active' => true,
            ]
        );

        Subscription::updateOrCreate(
            ['name' => 'الباقة الذهبية'],
            [
                'name' => 'الباقة الذهبية',
                'type' => 'gold',
                'description' => 'باقة ذهبية شاملة',
                'price' => 399.00,
                'duration_days' => 30,
                'features' => ['دعم فني 24/7', 'لوحة تحكم', 'تقارير متقدمة', 'دعم اولوية', 'ميزات حصرية'],
                'is_active' => true,
            ]
        );
    }
}
