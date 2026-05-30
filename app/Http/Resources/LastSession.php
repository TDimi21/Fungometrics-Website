<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class LastSession extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $relation = collect();

        if ($this->type === PracticeTypes::BATTING->value) {
            $batting  = $this->batting          ?? collect();
            $scripted = $this->scriptedBpSwings ?? collect();
            $relation = $batting->count() > 0 ? $batting : $scripted;
        } elseif ($this->type === PracticeTypes::BULLPEN->value) {
            $relation = $this->bullpen ?? collect();
        } elseif ($this->type === PracticeTypes::CAGE->value) {
            $relation = $this->cage ?? collect();
        } elseif ($this->type === PracticeTypes::LIVE_AB->value) {
            $relation = $this->live ?? collect();
        } elseif ($this->type === PracticeTypes::TRAINING->value && $this->modes === PracticeModes::LONG_TOSS->value) {
            $relation = $this->long_toss ?? collect();
        } elseif ($this->type === PracticeTypes::TRAINING->value && $this->modes === PracticeModes::WEIGHT_BALL->value) {
            $relation = $this->weight_ball ?? collect();
        } elseif ($this->type === PracticeTypes::TRAINING->value && $this->modes === PracticeModes::EXIT_VELOCITY->value) {
            $relation = $this->exit_velocity ?? collect();
        }

        return [
            "id"           => $this->id,
            "is_completed" => $this->is_completed,
            "start"        => $this->start,
            "type"         => $this->type,
            "mode"         => $this->modes,
            "date"         => $this->created_at,
            "created_at"   => $this->created_at,
            "updated_at"   => $this->updated_at,
            "end_note"     => $this->end_note,
            "lineup"       => ($this->lineup ?? collect())->map(fn ($element) => [
                'name' => [
                    'first' => $element->user->profile->first_name,
                    'last'  => $element->user->profile->last_name,
                    'full'  => $element->user->profile->first_name . " " . $element->user->profile->last_name,
                ],
                'id'               => $element->user->id,
                'picture'          => $element->user->profile->picture,
                'sort'             => $element->sort,
                'number_in_shirt'  => $element->user->player->number_in_shirt ?? 0,
                'batting'          => $element->is_batting,
            ]),
            "balls"        => $relation->count(),
            "is_scripted"  => $this->type === PracticeTypes::BATTING->value
                && ($this->batting ?? collect())->count() === 0
                && ($this->scriptedBpSwings ?? collect())->count() > 0,
        ];
    }
}
