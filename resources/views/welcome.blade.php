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
    </body>
</html>
