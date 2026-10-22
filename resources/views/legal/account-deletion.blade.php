@extends('legal.layout')
@section('title', 'Delete Your FMTRX Account')
@section('content')
<p>In the FMTRX iOS app, open More, choose Delete Account, verify your password, type DELETE, and confirm the destructive action. The confirmation expires after ten minutes and can only be used once.</p>
<p>Deletion signs out every device, revokes access tokens, removes team memberships, deletes referenced profile photos, and removes direct profile identifiers, including date of birth. Invitation credentials are revoked, pending communications are suppressed, and associated personal email snapshots are cleared. Limited numeric training history and billing/security records may remain linked to the deleted account’s internal identifier. Email already sent cannot be recalled.</p>
<p>Deleting FMTRX does not cancel an App Store subscription. Cancel or manage it separately in <a href="{{ config('legal.apple_subscriptions_url') }}">Apple Subscriptions</a>.</p>
@endsection
