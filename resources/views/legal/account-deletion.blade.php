@extends('legal.layout')
@section('title', 'Delete Your FMTRX Account')
@section('content')
<p>In the FMTRX iOS app, open Settings, choose Delete Account, verify your password, type DELETE, and confirm the destructive action. The confirmation expires after ten minutes and can only be used once.</p>
<p>Deletion signs out every device, revokes access tokens, removes team memberships, deletes referenced profile photos, and anonymizes retained assessment, session, billing, and audit records according to legal and fraud-prevention requirements.</p>
<p>Deleting FMTRX does not cancel an App Store subscription. Cancel or manage it separately in <a href="{{ config('legal.apple_subscriptions_url') }}">Apple Subscriptions</a>.</p>
@endsection
