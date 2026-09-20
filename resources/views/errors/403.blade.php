<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Akses dibatasi | {{ config('app.name', 'CRM Villa Merah') }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #172033;
            background:
                radial-gradient(circle at 15% 15%, rgba(14, 165, 233, .12), transparent 28rem),
                linear-gradient(145deg, #f8fafc 0%, #eef4f9 100%);
        }

        .card {
            width: min(100%, 560px);
            padding: 40px;
            border: 1px solid #dbe4ee;
            border-radius: 24px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 24px 60px rgba(15, 39, 65, .12);
        }

        .icon {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            margin-bottom: 24px;
            border-radius: 16px;
            color: #b45309;
            background: #fff7ed;
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(28px, 5vw, 38px);
            line-height: 1.15;
            letter-spacing: -.03em;
        }

        p {
            margin: 0;
            color: #64748b;
            font-size: 16px;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 20px;
            border: 1px solid #0b74b8;
            border-radius: 12px;
            color: #fff;
            background: #0b74b8;
            font-weight: 700;
            text-decoration: none;
        }

        .button:hover {
            background: #075f98;
        }

        .help {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e6edf4;
            font-size: 14px;
        }

        @media (max-width: 540px) {
            .card {
                padding: 28px 24px;
                border-radius: 18px;
            }

            .button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="card" role="main" aria-labelledby="access-title">
        <div class="icon" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                <path d="M12 14v2"></path>
            </svg>
        </div>

        <h1 id="access-title">Akses dibatasi</h1>
        <p>Anda tidak memiliki izin untuk membuka halaman ini. Silakan kembali ke dashboard atau hubungi Superadmin apabila Anda memerlukan akses.</p>

        <div class="actions">
            <a class="button" href="{{ url('/admin/dashboard') }}">Kembali ke dashboard</a>
        </div>

        <p class="help">Hak akses ditentukan berdasarkan peran pengguna dan kebijakan keamanan sistem.</p>
    </main>
</body>
</html>
