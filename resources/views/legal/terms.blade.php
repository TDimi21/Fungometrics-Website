@extends('legal.layout')
@section('title', 'Terms of Use')
@section('content')
<p>FMTRX provides baseball training and assessment tools. It is not medical advice. You are responsible for account security, lawful use, and the accuracy of information you enter.</p>
<h2>Subscriptions</h2>
<p>Subscriptions purchased through Apple are charged to your Apple ID, renew automatically unless canceled at least 24 hours before the end of the current period, and are managed in <a href="{{ config('legal.apple_subscriptions_url') }}">Apple Subscriptions</a>. Deleting an FMTRX account does not cancel an Apple subscription.</p>
<p>Access continues through the paid entitlement period unless Apple issues a refund. Apple controls billing, cancellation, and refund decisions for App Store purchases.</p>
@endsection
