<?php
namespace App\Http\Controllers\Api\Player;
use App\Http\Controllers\Controller;
use App\Services\Security\PlayerContactVerification;
use Illuminate\Http\Request;
class VerifyPlayerContact extends Controller {
    public function verify(Request $request,PlayerContactVerification $service) {
        $d=$request->validate(['challenge_id'=>'required|uuid','code'=>'required|string|regex:/^[0-9]{6}$/']);
        return response()->json(['status'=>'success','data'=>$service->verify($d['challenge_id'],$d['code'])])->header('Cache-Control','no-store');
    }
    public function confirm(Request $request,PlayerContactVerification $service) {
        $d=$request->validate(['challenge_id'=>'required|uuid','selection_token'=>'required|string|size:64','player_id'=>'required|uuid']);
        return response()->json(['status'=>'success','data'=>$service->confirm($d['challenge_id'],$d['selection_token'],$d['player_id'])])->header('Cache-Control','no-store');
    }
}
