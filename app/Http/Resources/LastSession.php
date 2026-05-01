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

        if($this->type === PracticeTypes::BATTING->value) {
            $relation = $this->batting;
        } elseif($this->type === PracticeTypes::BULLPEN->value) {
            $relation = $this->bullpen;
        } elseif($this->type === PracticeTypes::CAGE->value) {
            $relation = $this->cage;
        } elseif($this->type === PracticeTypes::LIVE_AB->value) {
            $relation = $this->live;
        } elseif($this->type === PracticeTypes::LONG_TOSS->value && $this->modes === PracticeModes::TRAINING->value) {
            $relation = $this->long_toss;
        } elseif($this->type === PracticeTypes::WEIGHT_BALL->value && $this->modes === PracticeModes::TRAINING->value) {
            $relation = $this->weight_ball;
        } elseif($this->type === PracticeTypes::EXIT_VELOCITY->value && $this->modes === PracticeModes::TRAINING->value) {
            $relation = $this->exit_velocity;
        }

        return [
            "id" => $this->id,
            "is_completed" => $this->is_completed,
            "start" => $this->start,
            "type" => $this->type,
            "mode" => $this->modes,
            "date"=>$this->created_at,
            "lineup" => $this->lineup->map(fn ($element) => [
                'name'=>[
                    'first'=>$element->user->profile->first_name,
                    'last'=>$element->user->profile->last_name,
                    'full'=>$element->user->profile->first_name." ".$element->user->profile->last_name,
                ],
                'id'=>$element->user->id,
                'picture'=>$element->user->profile->picture,
                'sort'=>$element->sort,
                'number_in_shirt'=>$element->user->player->number_in_shirt??0,
                'batting'=>$element->is_batting,
            ]),
            "balls"=>$relation->count()
        ];
    }
}
