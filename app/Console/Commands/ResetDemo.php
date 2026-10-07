<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDemo extends Command
{
    protected $signature = 'crm:reset-demo {--force : Required; confirms that ALL leads, customers and non-demo users will be deleted}';

    protected $description = 'Wipe all CRM data and restore the demo dataset and demo account credentials';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('This deletes ALL leads, customers, tokens, sessions and every non-demo user.');
            $this->line('Re-run with --force to confirm.');

            return self::FAILURE;
        }

        $demoEmails = array_keys(config('app.demo_accounts'));

        DB::transaction(function () use ($demoEmails) {
            Lead::withTrashed()->forceDelete();
            Customer::withTrashed()->forceDelete();
            DB::table('personal_access_tokens')->delete();
            DB::table('sessions')->delete();
            User::whereNotIn('email', $demoEmails)->delete();
        });

        $this->callSilently('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        $this->info(sprintf(
            'Demo data restored: %d users, %d leads, %d customers.',
            User::count(), Lead::count(), Customer::count(),
        ));

        return self::SUCCESS;
    }
}
