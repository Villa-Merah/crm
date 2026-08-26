<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR WhatsApp belum siap · SIVMI CRM</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f6f8fc; color: #0f172a; }
        main { width: min(100%, 560px); padding: 32px; border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; box-shadow: 0 18px 48px rgba(15, 23, 42, .08); }
        .icon { display: grid; width: 48px; height: 48px; place-items: center; border-radius: 14px; background: #fff7ed; color: #f59e0b; font-size: 24px; }
        h1 { margin: 20px 0 8px; font-size: 24px; }
        p { margin: 0; color: #64748b; line-height: 1.65; }
        .status { margin: 20px 0; padding: 12px 14px; border-radius: 12px; background: #f1f5f9; color: #334155; font-size: 14px; }
        nav { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        a { padding: 11px 16px; border-radius: 12px; text-decoration: none; font-weight: 600; }
        .primary { background: #1476b8; color: #fff; }
        .secondary { border: 1px solid #cbd5e1; color: #334155; }
    </style>
</head>
<body>
<main>
    <div class="icon">!</div>
    <h1>QR WhatsApp belum siap</h1>
    <p>Session sedang dipulihkan atau koneksi ke WhatsApp terputus. Tunggu beberapa detik, kemudian coba tampilkan QR terbaru.</p>
    <div class="status">Session: {{ $account->session_name }} · Status WAHA: {{ $status }}</div>
    <nav>
        <a class="primary" href="{{ route('waha.accounts.qr', $account) }}">Coba lagi</a>
        <a class="secondary" href="{{ url('/admin/koneksi-whatsapp') }}">Kembali ke koneksi</a>
    </nav>
</main>
</body>
</html>
