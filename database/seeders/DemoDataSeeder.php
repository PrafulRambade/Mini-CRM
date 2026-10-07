<?php

namespace Database\Seeders;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadConversionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Deterministic demo dataset with no Faker dependency, so it also runs on
 * production installs (composer --no-dev). Used by DatabaseSeeder and by
 * `php artisan crm:reset-demo`.
 */
class DemoDataSeeder extends Seeder
{
    private const FIRST = ['Aarav', 'Diya', 'Rohan', 'Ananya', 'Vikram', 'Priya', 'Kabir', 'Meera', 'Arjun', 'Isha',
        'Nikhil', 'Sneha', 'Rahul', 'Pooja', 'Karan', 'Neha', 'Aditya', 'Riya', 'Siddharth', 'Kavya',
        'Manish', 'Tanvi', 'Yash', 'Shreya'];

    private const LAST = ['Sharma', 'Patel', 'Mehta', 'Iyer', 'Reddy', 'Kulkarni', 'Desai', 'Joshi', 'Nair', 'Gupta',
        'Kapoor', 'Bose', 'Rao', 'Malhotra', 'Shah', 'Pillai'];

    private const COMPANIES = ['Acme Retail', 'Blue Orbit Tech', 'Sunrise Logistics', 'Greenleaf Foods', 'Nimbus Cloud',
        'Vertex Finserv', 'Lotus Interiors', 'Pixel Forge Studio', 'Harbor Pharma', 'Zenith Motors',
        'Cedar Education', 'Quantum Analytics', null, null];

    private const NOTES = [
        'Requested a product demo next week.',
        'Interested in the annual plan; waiting on budget approval.',
        'Asked for a detailed quotation by email.',
        'Referred by an existing customer.',
        'Came through the pricing page enquiry form.',
        'Prefers a call after 4 PM.',
        null,
    ];

    public function run(LeadConversionService $conversion): void
    {
        mt_srand(20261007);

        $users = $this->seedDemoUsers();
        $admin = $users['admin'];
        $n = 0;

        foreach ($users['sales'] as $owner) {
            // Open and lost leads.
            for ($i = 0; $i < 20; $i++) {
                $status = [LeadStatus::New, LeadStatus::New, LeadStatus::InProgress, LeadStatus::InProgress, LeadStatus::Lost][mt_rand(0, 4)];
                $this->makeLead($n++, $owner, $admin, $status);
            }

            // Won leads go through the real conversion path.
            for ($i = 0; $i < 4; $i++) {
                $lead = $this->makeLead($n++, $owner, $admin, LeadStatus::InProgress);
                $conversion->convert($lead, $admin->id);

                $convertedAt = $lead->created_at->copy()->addDays(mt_rand(1, 6))->min(now());
                $lead->forceFill(['converted_at' => $convertedAt])->saveQuietly();
            }
        }
    }

    /**
     * Create (or restore) the demo accounts with their known credentials.
     *
     * @return array{admin: User, sales: list<User>}
     */
    private function seedDemoUsers(): array
    {
        $admin = null;
        $sales = [];

        foreach (config('app.demo_accounts') as $email => $attrs) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $attrs['name'];
            $user->password = config('app.demo_password');
            $user->forceFill(['role' => $attrs['role'], 'is_active' => true, 'email_verified_at' => now()])->save();

            if ($attrs['role'] === 'admin') {
                $admin = $user;
            } else {
                $sales[] = $user;
            }
        }

        return ['admin' => $admin, 'sales' => $sales];
    }

    private function makeLead(int $n, User $owner, User $admin, LeadStatus $status): Lead
    {
        $first = self::FIRST[$n % count(self::FIRST)];
        $last = self::LAST[($n * 7) % count(self::LAST)];
        $company = self::COMPANIES[mt_rand(0, count(self::COMPANIES) - 1)];
        $createdAt = Carbon::now()->subDays(mt_rand(0, 29))->subMinutes(mt_rand(0, 600));
        $open = in_array($status, [LeadStatus::New, LeadStatus::InProgress], true);

        $lead = new Lead([
            'name' => "{$first} {$last}",
            'email' => strtolower("{$first}.{$last}{$n}@example.com"),
            'phone' => sprintf('+91 98%03d %05d', mt_rand(0, 999), mt_rand(0, 99999)),
            'company' => $company,
            'source' => LeadSource::cases()[mt_rand(0, 2)],
            'status' => $status,
            'assigned_to' => $owner->id,
            // Mix of overdue, due soon and later follow-ups for open leads.
            'follow_up_date' => $open && mt_rand(0, 9) < 8 ? now()->addDays(mt_rand(-4, 20))->toDateString() : null,
            'notes' => self::NOTES[mt_rand(0, count(self::NOTES) - 1)],
        ]);
        $lead->forceFill(['created_by' => $admin->id, 'created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $lead;
    }
}
