@extends('legal.layout')
@section('title', 'Support')
@section('content')
<p>For account, privacy, subscription-access, or technical support, contact <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>. Include the app version and a description of the issue; never send a password or authentication token.</p>
<p>Apple billing, cancellation, and refund requests must be handled through <a href="{{ config('legal.apple_subscriptions_url') }}">Apple Subscriptions</a> or Apple Support.</p>
@endsection
