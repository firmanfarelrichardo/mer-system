<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard — Sistem Pelaporan Insiden</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background:#f0f4f8; margin:0; padding:2rem; }
        .container { max-width: 800px; margin: 0 auto; background:#fff; border-radius:12px; padding:2rem; box-shadow:0 4px 24px rgba(0,0,0,.08); }
        h1 { color:#1e3a5f; }
        p  { color:#475569; margin-top:.5rem; }
        .meta { margin-top:1rem; font-size:.85rem; color:#94a3b8; }
        form button {
            margin-top: 1.5rem;
            padding: .6rem 1.25rem;
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .9rem;
            cursor: pointer;
        }
        form button:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dashboard</h1>
        <p>Selamat datang, <strong>{{ Auth::user()->nama_lengkap }}</strong>.</p>

        <div class="meta">
            Peran: <strong>{{ implode(', ', Auth::user()->daftarPeran()) ?: '—' }}</strong>
            &nbsp;|&nbsp;
            Login terakhir: {{ Auth::user()->terakhir_login_pada?->diffForHumans() ?? '—' }}
        </div>

        {{-- Tombol keluar — gunakan POST agar sesi diinvalidasi dengan aman --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </div>
</body>
</html>
