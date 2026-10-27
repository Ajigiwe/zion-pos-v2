<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="authed" content="0">
    <title>{{ store_info()['name'] }} — Sign in</title>
    <link rel="manifest" href="/manifest.webmanifest?v=2">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="bg-body-tertiary">
@yield('content')
<script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/js/offline.js"></script>
@stack('scripts')
</body>
</html>
