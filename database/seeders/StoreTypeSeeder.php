<?php

namespace Database\Seeders;

use App\Models\StoreType;
use Illuminate\Database\Seeder;

class StoreTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (StoreType::TYPES as $name => $config) {
            StoreType::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $config['display_name'],
                    'display_name_en' => $config['display_name_en'],
                    'required_attributes' => $config['required_attributes'],
                    'optional_attributes' => $config['optional_attributes'],
                    'ai_template' => $config['ai_template'],
                    'order_questions' => json_encode($config['order_questions']),
                    'requires_stock' => $config['requires_stock'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Store types seeded successfully!');
        $this->command->table(
            ['Name', 'Display Name', 'Required Attributes'],
            collect(StoreType::TYPES)->map(fn($c, $n) => [
                $n,
                $c['display_name'],
                implode(', ', $c['required_attributes']) ?: '-'
            ])->toArray()
        );
    }
}
