<?php
namespace App\Services\Security;
use App\Services\SendSmsService;
use Illuminate\Support\Facades\Mail;
class PlayerClaimCodeDelivery {
    public function send(string $contact, string $code): void {
        $message="Your FMTRX profile verification code is {$code}. It expires in 10 minutes. Do not share this code. If you did not request it, ignore this message.";
        if (str_contains($contact,'@')) {
            if (in_array(config('mail.default'), ['log','array'],true) && !app()->environment('testing')) throw new \RuntimeException('Mail delivery unavailable');
            Mail::raw($message, fn($mail)=>$mail->to($contact)->subject('Verify your FMTRX player profile'));
        } else {
            $phone=str_starts_with($contact,'+') ? $contact : '+'.(strlen($contact)===10 ? '1' : '').$contact;
            if (!app(SendSmsService::class)->sendSms($phone,$message,type:'profile_verification',sensitive:true)) throw new \RuntimeException('SMS delivery unavailable');
        }
    }
}
