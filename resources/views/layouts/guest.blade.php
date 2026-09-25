<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Budgetra</title>
    <link rel="icon" type="image/png" href="{{ asset('systemicons/budgetraicon.png') }}?v={{ filemtime(public_path('systemicons/budgetraicon.png')) }}">
    {{-- Versioned like the app layouts: without it the browser serves whatever
     copy of style.css it already had, and every change to this page's styling
     needs a hard refresh before anyone sees it. --}}
<link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-body">
    @yield('content')
</body>
</html>
