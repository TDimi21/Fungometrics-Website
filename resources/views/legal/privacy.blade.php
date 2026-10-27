@extends('legal.layout')
@section('title', 'Privacy Policy')
@section('effective_date', 'October 27, 2026')
@section('content')
<p>FMTRX processes account identity and contact details, baseball assessment and session data, fitness information, photos, notes, device diagnostics, usage data, and purchase status to provide and secure the service.</p>
<p>We also record profile-creation and account-setup acknowledgments, including the accepting account, affected player and team, policy versions, acknowledgment text, and acceptance time. Birth dates supplied during setup are used to determine self-service account eligibility. An authorization checkbox is not a record of verified parental consent.</p>
<p>We do not sell personal information or use FMTRX data for cross-company advertising tracking. Service providers process only the data needed for hosting, authentication, diagnostics, communications, and subscription entitlement management.</p>
<p>You may request access, correction, or deletion through the app or <a href="{{ config('legal.support_url') }}">support</a>. Required billing and security audit records may be retained in anonymized or legally required form.</p>
@endsection
