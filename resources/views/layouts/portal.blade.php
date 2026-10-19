<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Platform kerja penjaminan mutu pendidikan dengan bukti dan sumber yang dapat ditinjau.">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/portal.css', 'resources/js/portal.js'])
</head>
<body>
    @yield('content')
</body>
</html>
