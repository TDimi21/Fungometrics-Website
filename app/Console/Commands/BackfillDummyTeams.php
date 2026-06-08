<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CoachTeam;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Models\Concerns\UserTypes;
use App\Services\CreateServiceData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillDummyTeams extends Command
{
    protected $signature   = 'fmtrx:backfill-dummy-teams {--dry-run : Show what would be created without saving}';
    protected $description = 'Create a per-team dummy opponent (Scouts) for every existing real team that does not have one yet.';

    private const DUMMY_PLAYERS = [
        ['first' => 'Alex',    'last' => 'Reyes',  'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Jordan',  'last' => 'Miller', 'bats' => 'L', 'throws' => 'L'],
        ['first' => 'Sam',     'last' => 'Torres', 'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Chris',   'last' => 'Clark',  'bats' => 'L', 'throws' => 'R'],
        ['first' => 'Taylor',  'last' => 'Hill',   'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Drew',    'last' => 'Young',  'bats' => 'L', 'throws' => 'L'],
        ['first' => 'Casey',   'last' => 'Scott',  'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Blake',   'last' => 'Price',  'bats' => 'L', 'throws' => 'R'],
        ['first' => 'Riley',   'last' => 'Brooks', 'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Logan',   'last' => 'Flores', 'bats' => 'L', 'throws' => 'L'],
        ['first' => 'Parker',  'last' => 'Ward',   'bats' => 'R', 'throws' => 'R'],
        ['first' => 'Cameron', 'last' => 'Diaz',   'bats' => 'L', 'throws' => 'R'],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // All real (non-dummy) teams that don't already have a dummy opponent
        $realTeams = Team::where('is_dummy', false)
            ->whereNull('deleted_at')
            ->whereNotIn('id', function ($q) {
                $q->select('owner_team_id')->from('teams')->where('is_dummy', true)->whereNotNull('owner_team_id');
            })
            ->get();

        if ($realTeams->isEmpty()) {
            $this->info('All teams already have a dummy opponent. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Creating dummy opponents for {$realTeams->count()} team(s)...");

        foreach ($realTeams as $realTeam) {
            // Find first coach for this team so we can link the dummy team to them
            $coachTeam = CoachTeam::where('team_id', $realTeam->id)->first();
            if (!$coachTeam) {
                $this->warn("  Skipping team [{$realTeam->name}] — no coach linked.");
                continue;
            }

            $this->line("  → {$realTeam->name} (id: {$realTeam->id})");

            if ($dryRun) continue;

            try {
                DB::beginTransaction();

                $dummyTeam = (new CreateServiceData(new Team()))->handle([
                    'name'          => $realTeam->name . ' Scouts',
                    'logo'          => '',
                    'state'         => 'SIM',
                    'zip'           => '00000',
                    'join_code'     => Team::generateJoinCode(),
                    'is_dummy'      => true,
                    'owner_team_id' => $realTeam->id,
                ]);

                (new CreateServiceData(new CoachTeam()))->handle([
                    'coach_id' => $coachTeam->coach_id,
                    'team_id'  => $dummyTeam->id,
                    'is_main'  => false,
                ]);

                foreach (self::DUMMY_PLAYERS as $i => $p) {
                    $user = (new CreateServiceData(new User()))->handle([
                        'phone'    => 'dummy-' . $dummyTeam->id . '-' . $i,
                        'type'     => UserTypes::PLAYER->value,
                        'status'   => true,
                        'is_dummy' => true,
                    ]);

                    (new CreateServiceData(new Profile()))->handle([
                        'user_id'    => $user->id,
                        'first_name' => $p['first'],
                        'last_name'  => $p['last'],
                    ]);

                    (new CreateServiceData(new Player()))->handle([
                        'user_id'    => $user->id,
                        'hit_side'   => $p['bats'],
                        'throw_side' => $p['throws'],
                    ]);

                    (new CreateServiceData(new PlayerTeam()))->handle([
                        'user_id' => $user->id,
                        'team_id' => $dummyTeam->id,
                    ]);
                }

                DB::commit();
                $this->line("    ✓ Created [{$dummyTeam->name}] with 12 dummy players");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("    ✗ Failed for [{$realTeam->name}]: " . $e->getMessage());
                Log::error('BackfillDummyTeams: ' . $e->getMessage());
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
