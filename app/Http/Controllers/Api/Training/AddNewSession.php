<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\AddNewSessionRequest;
use App\Http\Resources\Api\PracticeSessionResource;
use App\Models\CagePracticeMeta;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Services\CreateServiceData;
use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddNewSession extends Controller
{
    /**
     * @param AddNewSessionRequest $request
     * @return JsonResponse
     */
    public function __invoke(AddNewSessionRequest $request): JsonResponse
    {
        try {
            $players = [];
            DB::beginTransaction();
            $dataRequest = $request->validated();
            $longTossSetup=$dataRequest['long_toss_setup']??[];
            unset($dataRequest['long_toss_setup']);
            $sharingTeams = $dataRequest['share_with_team_ids'] ?? (isset($dataRequest['share_with_team_id']) ? [$dataRequest['share_with_team_id']] : []);
            unset($dataRequest['share_with_team_id'], $dataRequest['share_with_team_ids']);
            $dataRequest['started'] = Carbon::now();
            if ( ! isset($dataRequest['team'])) {
                $dataRequest['user_id'] = Auth::id();
            }
            if (isset($dataRequest['team'])) {
                $dataRequest['team_id'] = $dataRequest['team'];
            }
            if (count($sharingTeams) > 0) {
                $role = $request->user()->type;
                abort_unless(($role instanceof \BackedEnum ? $role->value : $role) === 'player', 403);
                abort_unless(\App\Models\PlayerTeam::where('user_id', Auth::id())->whereIn('team_id', $sharingTeams)->where('actual', true)->distinct()->count('team_id') === count($sharingTeams), 403);
                $dataRequest['user_id'] = Auth::id();
                $dataRequest['team_id'] = $sharingTeams[0];
                $dataRequest['is_player_shared'] = true;
            }
            $dataRequest['modes'] = $dataRequest['modes'] ?? PracticeModes::HIT_OR_PITCH->value;
            $dataRequest['type'] = $dataRequest['type'] ?? PracticeTypes::TRAINING->value;
            $dataRequest['is_scripted'] = (bool) ($dataRequest['scripted'] ?? false);
            unset($dataRequest['scripted']);

            $plannerPlan = null;
            $plannerItem = null;
            if (!empty($dataRequest['planner_plan_id'])) {
                $plannerPlan = \App\Models\DailyPlan::where('status','published')->lockForUpdate()->findOrFail($dataRequest['planner_plan_id']);
                abort_unless($plannerPlan->assignments()->where('user_id', Auth::id())->where('schedule_status','active')->exists(),403);
                abort_unless(in_array($plannerPlan->team_id, $sharingTeams, true) && count($dataRequest['players'])===1 && $dataRequest['players'][0]['id']===Auth::id(),422,'Select the assigned workout team under sharing to link this session to your workout.');
                $plannerItem = collect($plannerPlan->bucketsFor((string)Auth::id()))->flatMap(fn($b)=>$b['items']??[])->firstWhere('id',$dataRequest['planner_item_id']);
                abort_unless($plannerItem && app(\App\Services\Planner\LinkedSessionRegistry::class)->matches($plannerItem['metadata']['session_type']??'',new Practice($dataRequest)),422,'Session type does not match this workout block.');
                $link=DB::table('workout_session_links')->where(['plan_id'=>$plannerPlan->id,'user_id'=>Auth::id(),'item_id'=>$plannerItem['id']])->first();
                if($link) {
                    $practice=Practice::findOrFail($link->practice_id);
                    abort_unless($practice->is_player_shared && $practice->sharedTeams()->where('teams.id', $plannerPlan->team_id)->exists() && ($practice->user_id===Auth::id() || $practice->lineup()->where('user_id',Auth::id())->exists()),403);
                    DB::commit();
                    return response()->json(['status'=>'success','data'=>new PracticeSessionResource(['practice'=>$practice,'players'=>$practice->lineup()->get(),'meta'=>CagePracticeMeta::where('practice_id',$practice->id)->first()])],200);
                }
                if($plannerPlan->settings['readiness_required']??false)abort_unless(\App\Models\DailyPlanProgress::where('plan_id',$plannerPlan->id)->where('user_id',Auth::id())->first()?->readiness,422,'Save your pre-training check-in before starting this session.');
                $dataRequest['user_id']=Auth::id();
                unset($dataRequest['planner_plan_id'],$dataRequest['planner_item_id']);
            }
            $practice = (new CreateServiceData(new Practice()))->handle($dataRequest);
            if (count($sharingTeams) > 0) $practice->sharedTeams()->sync($sharingTeams);
            if ($practice->type === 'B' && $practice->script_plan) {
                \App\Models\ScriptedBpPlan::create(['practice_id' => $practice->id, 'rounds' => $practice->script_plan]);
            }
            $metaCage = null;
            if (isset($dataRequest['cage'])) {
                $metaCage = (new CreateServiceData(new CagePracticeMeta()))->handle([
                    'practice_id' => $practice->id,
                    'height_ft' => $dataRequest['cage']['height']['ft'],
                    'height_inch' => $dataRequest['cage']['height']['inch'],
                    'width_ft' => $dataRequest['cage']['width']['ft'],
                    'width_inch' => $dataRequest['cage']['width']['inch'],
                    'length_ft' => $dataRequest['cage']['length']['ft'],
                    'length_inch' => $dataRequest['cage']['length']['inch'],
                ]);
            }

            foreach ($dataRequest['players'] as $player) {
                $players[] = (new CreateServiceData(new PracticeLineUp()))
                    ->handle([
                        'practice_id' => $practice->id,
                        'user_id' => $player['id'],
                        'sort' => $player['sort'],
                        'is_batting' => $dataRequest['type'] !== PracticeTypes::BULLPEN->value && $dataRequest['type']
                          !== PracticeTypes::TRAINING->value,
                        'is_pitching' => $dataRequest['type'] === PracticeTypes::BULLPEN->value && $dataRequest['type']
                          !== PracticeTypes::TRAINING->value,
                    ]);
            }
            if($longTossSetup) app(\App\Services\LongToss\SessionSetup::class)->create($practice,$longTossSetup);
            if($plannerPlan) DB::table('workout_session_links')->insert(['id'=>(string)\Illuminate\Support\Str::uuid(),'plan_id'=>$plannerPlan->id,'user_id'=>Auth::id(),'item_id'=>$plannerItem['id'],'practice_id'=>$practice->id,'type'=>$plannerItem['metadata']['session_type'],'created_for_workout'=>true,'created_at'=>now(),'updated_at'=>now()]);
            $response = [
                'code' => '006',
                'message' => 'add new session training',
                'status' => 'success',
                'data' => new PracticeSessionResource([
                    'practice' => $practice,
                    'players' => $players,
                    'meta' => $metaCage
                ]),
            ];
            DB::commit();

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            DB::rollBack(); throw $exception;
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '006-E',
                'message' => 'error to create a session training',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
