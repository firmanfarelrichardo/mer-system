<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Pelaporan Insiden Medication Errors')</title>

    {{-- Cegah mesin pencari mengindeks halaman autentikasi --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- Aset Vite (CSS + JS); sesuaikan titik masuk dengan vite.config.js --}}
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
        }
        .card-header { text-align: center; margin-bottom: 1.75rem; }
        .card-header h1 { font-size: 1.25rem; color: #1e3a5f; font-weight: 700; }
        .card-header p  { font-size: .85rem; color: #64748b; margin-top: .25rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: .85rem; font-weight: 600; color: #334155; margin-bottom: .35rem; }
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: .6rem .85rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: .9rem;
            transition: border-color .2s;
            outline: none;
        }
        input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .is-invalid { border-color: #ef4444 !important; }
        .invalid-feedback { color: #ef4444; font-size: .8rem; margin-top: .3rem; }
        .btn-primary {
            width: 100%;
            padding: .7rem;
            background: #1e3a5f;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
            margin-top: .5rem;
        }
        .btn-primary:hover { background: #2c4f84; }
        .alert { padding: .75rem 1rem; border-radius: 8px; font-size: .875rem; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .captcha-wrap { display: flex; gap: .75rem; align-items: center; }
        .captcha-wrap img { border-radius: 6px; border: 1px solid #e2e8f0; cursor: pointer; height: 48px; }
        .captcha-wrap input { flex: 1; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h1>🏥 Sistem Pelaporan Insiden</h1>
            <p>Sistem Pelaporan Kesalahan Pengobatan</p>
        </div>

        {{-- Pesan kilat dari sesi --}}
        @if (session('sukses'))
            <div class="alert alert-success" role="alert">{{ session('sukses') }}</div>
        @endif

        @yield('content')
    </div>
</body>
</html>
