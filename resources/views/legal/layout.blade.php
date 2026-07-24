<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — FMTRX</title>
    <style>
        body { background:#07152f; color:#f4f7fb; font:16px/1.6 system-ui,sans-serif; margin:0; }
        main { max-width:760px; margin:auto; padding:48px 24px 80px; }
        a { color:#65c7ff; } h1,h2 { line-height:1.2; } small { color:#aab6c8; }
    </style>
</head>
<body><main>
    <a href="/">FMTRX</a>
    <h1>@yield('title')</h1>
    <small>Effective July 23, 2026</small>
    @yield('content')
    <p>
        <a href="{{ config('legal.privacy_url') }}">Privacy</a> ·
        <a href="{{ config('legal.terms_url') }}">Terms</a> ·
        <a href="{{ config('legal.support_url') }}">Support</a> ·
        <a href="{{ config('legal.account_deletion_url') }}">Delete account</a>
    </p>
</main></body>
</html>
