<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="10">
    <title>{{ __('QR Absensi') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #09090b; padding: 1.5rem; }
        .qr { background: #fff; padding: 1rem; border-radius: 1rem; line-height: 0; }
        .qr svg { width: min(70vw, 320px); height: auto; display: block; }
    </style>
</head>
<body>
    <div class="qr">{!! $svg !!}</div>
</body>
</html>
