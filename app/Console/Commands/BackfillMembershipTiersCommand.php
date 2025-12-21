<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MembershipTierService;
use Illuminate\Console\Command;

class BackfillMembershipTiersCommand extends Command
{
    protected $signature = 'users:backfill-membership-tiers';
    protected $description = 'Assign membership tiers based on loyalty points for existing users';

    public function __construct(
        protected MembershipTierService $membershipTierService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $updated = 0;

        User::chunk(200, function ($users) use (&$updated) {
            foreach ($users as $user) {
                $tier = $this->membershipTierService->getAppropriateTier($user->loyalty_points ?? 0);
                if ($tier && $user->membership_tier_id !== $tier->membership_tier_id) {
                    $user->membership_tier_id = $tier->membership_tier_id;
                    $user->save();
                    $updated++;
                }
            }
        });

        $this->info("Backfill completed. Updated {$updated} users.");
        return Command::SUCCESS;
    }
}
