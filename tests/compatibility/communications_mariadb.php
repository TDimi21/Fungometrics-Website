<?php
/** Destructive ONLY to an explicitly named, empty, disposable LOCAL rehearsal database. */
declare(strict_types=1);

if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'fmtrx_communications_rehearsal' || getenv('DB_HOST') !== '127.0.0.1') {
    throw new RuntimeException('Use APP_ENV=testing, DB_HOST=127.0.0.1 and DB_DATABASE=fmtrx_communications_rehearsal only.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB, Schema, Mail, Notification, Queue};
use App\Models\TeamInvitation;

try {
Mail::fake(); Notification::fake(); Queue::fake();
config(['communications.enabled'=>false,'communications.invitations_enabled'=>false]);
if (DB::connection()->getDatabaseName() !== 'fmtrx_communications_rehearsal'
    || !str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '10.1.48-MariaDB')) {
    throw new RuntimeException('Wrong database or engine.');
}
if (count(DB::select('SHOW TABLES')) !== 0) throw new RuntimeException('Rehearsal database must be empty.');
if (DB::selectOne("SHOW VARIABLES LIKE 'explicit_defaults_for_timestamp'")->Value !== 'OFF') throw new RuntimeException('Rehearsal requires legacy timestamp defaults OFF.');
$check = function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; };
foreach (['2026_10_16_180000_create_team_invitations_table.php','2026_10_16_180100_create_jobs_table.php','2026_10_16_190000_create_communication_outbox.php'] as $file) {
    (require database_path('migrations/'.$file))->up();
}
$check(count(DB::select('SHOW TABLES')) === 5, 'three base migrations create all five tables');
$fixed = require database_path('migrations/2026_10_16_200000_fix_team_invitation_expiry.php');
$fixed->up();
$check(DB::selectOne("SHOW COLUMNS FROM team_invitations LIKE 'expires_at'")->Type === 'datetime', 'fresh installation uses DATETIME with legacy defaults OFF');
$record = TeamInvitation::create(['team_id'=>'rehearsal-team','invited_by'=>'rehearsal-coach','email'=>'test@example.com','role'=>'player','expires_at'=>now()->addDays(7)]);
$expiry = $record->expires_at->toDateTimeString();
$record->update(['delivery_status'=>'sending']);
$record->update(['delivery_status'=>'sent','sent_at'=>now()]);
$check($record->fresh()->expires_at->toDateTimeString() === $expiry, 'insert and delivery updates preserve expiry');
$record->update(['expires_at'=>now()->addDays(8),'generation'=>2]);
$renewed=$record->fresh()->expires_at->toDateTimeString();
$record->update(['delivery_status'=>'sent']);
$record->update(['accepted_at'=>now()]);
$check($record->fresh()->expires_at->toDateTimeString() === $renewed && $record->fresh()->state()==='accepted', 'explicit resend expiry survives acceptance');
// Simulate an already-migrated legacy schema, including its old implicit ON UPDATE behavior.
DB::statement('ALTER TABLE team_invitations MODIFY expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
$ids=[];
foreach (['active','expired','revoked','accepted'] as $state) {
    $row=TeamInvitation::create(['team_id'=>'rehearsal-team','invited_by'=>'rehearsal-coach','email'=>'test@example.com','role'=>'player','expires_at'=>$state==='expired'?now()->subDay():now()->addDays(7),'revoked_at'=>$state==='revoked'?now():null,'accepted_at'=>$state==='accepted'?now():null]);
    $ids[$state]=$row->id;
}
$before=DB::table('team_invitations')->orderBy('id')->get()->toJson();
$fixed->up();
$check($before===DB::table('team_invitations')->orderBy('id')->get()->toJson(), 'corrective migration preserves every stored invitation value');
foreach ($ids as $state=>$id) {
    $row=TeamInvitation::findOrFail($id);$expiry=$row->expires_at->toDateTimeString();
    $row->update(['delivery_status'=>'sent','sent_at'=>now()]);
    $check($row->fresh()->expires_at->toDateTimeString()===$expiry && $row->fresh()->state()===($state==='active'?'pending':$state), "$state invitation state and expiry remain stable after upgrade");
}
$fixed->up();$fixed->down();
$check(DB::selectOne("SHOW COLUMNS FROM team_invitations LIKE 'expires_at'")->Type === 'datetime', 'rerun and down do not restore unsafe timestamp behavior');
$beforeCode = DB::table('team_invitations')->orderBy('id')->get()->toJson();
(require database_path('migrations/2026_10_16_210000_add_claim_code_to_team_invitations.php'))->up();
$afterCode = DB::table('team_invitations')->orderBy('id')->get()->map(function ($row) { unset($row->claim_code_hash); return $row; })->toJson();
$check($beforeCode === $afterCode, 'manual claim-code migration preserves all existing invitation attributes');
$check(DB::table('team_invitations')->whereNotNull('claim_code_hash')->count() === 0, 'existing links gain no invented manual credentials');
Mail::assertNothingSent();Notification::assertNothingSent();
echo "MariaDB 10.1.48 migration/expiry rehearsal complete; no email sent.\n";

} catch (Throwable $error) {
    fwrite(STDERR, "Rehearsal failed: ".$error->getMessage()."\n");
    exit(1);
}
