<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

class PlayerClaimStatus extends Command
{
    protected $signature = 'player:claim-status {email : Email saved when the coach added the player}';
    protected $description = 'Read-only check of player claim eligibility and roster team codes (no credentials displayed).';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');
            return self::FAILURE;
        }
        $players = User::withTrashed()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->get();
        if ($players->isEmpty()) {
            $this->error('No account has this email in this server database. Check that the web and app use this same API.');
            return self::FAILURE;
        }
        foreach ($players as $player) {
            $this->table(['Account type', 'Active', 'Deleted', 'Login already created'], [[
                $player->type, $player->status ? 'Yes' : 'No', $player->trashed() ? 'Yes' : 'No', filled($player->password) ? 'Yes — use login/password recovery' : 'No',
            ]]);
            $rows = $player->team_players()->withTrashed()->get()->map(function ($membership): array {
                $team = Team::withTrashed()->find($membership->team_id);
                return [$team?->name ?? 'Missing team', $team?->join_code ?? 'No team code',
                    $membership->actual && !$membership->trashed() && $team && !$team->trashed() ? 'Yes' : 'No'];
            })->all();
            if (!$rows) {
                $this->warn('No roster membership: this profile cannot be claimed with a team code.');
            } else {
                $this->table(['Team', 'Team code', 'Active membership'], $rows);
            }
        }
        $this->info('Read-only check complete. No account, password, membership, or claim token was changed.');
        return self::SUCCESS;
    }
}
