<?php

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadConversionService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Password@123';

    public function run(LeadConversionService $conversion): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@crm.test',
            'password' => self::DEMO_PASSWORD,
        ]);

        $salesUsers = collect([
            ['name' => 'Sales One', 'email' => 'sales1@crm.test'],
            ['name' => 'Sales Two', 'email' => 'sales2@crm.test'],
        ])->map(fn (array $attrs) => User::factory()->sales()->create([
            ...$attrs,
            'password' => self::DEMO_PASSWORD,
        ]));

        $salesUsers->each(function (User $user) use ($admin, $conversion) {
            Lead::factory(20)->assignedTo($user)->create(['created_by' => $admin->id]);

            // Won leads go through the real conversion path.
            Lead::factory(4)->assignedTo($user)->status(LeadStatus::InProgress)->create(['created_by' => $admin->id])
                ->each(fn (Lead $lead) => $conversion->convert($lead, $admin->id));
        });
    }
}
