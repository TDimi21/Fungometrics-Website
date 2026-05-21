<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>fungometrics app</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700;800;900&display=swap"
              rel="stylesheet">

    </head>
    @vite(['resources/js/app.js','resources/css/app.css'])
    <body class="antialiased">
    <div id="app">
        <router-view></router-view>
    </div>
    <script>
        window.onerror = function(msg, src, line, col, err) {
            document.body.innerHTML = '<div style="background:#1a1a1a;color:#ff6b6b;font-family:monospace;padding:24px;min-height:100vh;white-space:pre-wrap;font-size:14px;"><b>JS ERROR</b>\n\n' + msg + '\n\nFile: ' + src + '\nLine: ' + line + '\n\n' + (err ? err.stack : '') + '</div>';
        };
        window.addEventListener('unhandledrejection', function(e) {
            document.body.innerHTML = '<div style="background:#1a1a1a;color:#ff6b6b;font-family:monospace;padding:24px;min-height:100vh;white-space:pre-wrap;font-size:14px;"><b>UNHANDLED PROMISE</b>\n\n' + (e.reason ? (e.reason.stack || e.reason) : e) + '</div>';
        });
    </script>
    </body>
</html>
