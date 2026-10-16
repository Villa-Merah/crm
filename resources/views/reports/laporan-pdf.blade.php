<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan kinerja {{ $organizationName }}</title>
    <style>
        @page { margin: 26px 30px 38px; }
        body { color: #243247; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .header { background: #075985; color: #fff; padding: 20px 22px; }
        .brand { color: #bae6fd; font-size: 9px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; }
        h1 { font-size: 21px; margin: 7px 0 4px; }
        .subtitle { color: #dbeafe; font-size: 10px; }
        .meta { color: #e0f2fe; font-size: 8px; margin-top: 10px; }
        .kpis { border-collapse: separate; border-spacing: 8px 0; margin: 14px -8px 20px; width: 100%; }
        .kpis td { background: #f1f7fb; border: 1px solid #dbe7ef; padding: 10px 12px; width: 25%; }
        .kpi-label { color: #64748b; font-size: 8px; }
        .kpi-value { color: #0f3e5a; font-size: 16px; font-weight: bold; margin-top: 5px; }
        .section { margin: 16px 0; }
        h2 { border-bottom: 2px solid #d7e8f2; color: #0f3e5a; font-size: 12px; margin: 0 0 8px; padding-bottom: 5px; }
        .hint { color: #64748b; font-size: 8px; margin: -3px 0 8px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th { background: #eaf3f8; color: #35536a; font-size: 8px; text-align: left; }
        table.data th, table.data td { border-bottom: 1px solid #dce5eb; padding: 6px 7px; vertical-align: middle; }
        table.data tr { page-break-inside: avoid; }
        .num { text-align: right !important; white-space: nowrap; }
        .muted { color: #718096; }
        .bar-track { background: #e8eef3; height: 8px; width: 100%; }
        .bar { background: #1476b8; height: 8px; }
        .bar-purple { background: #7455c7; }
        .bar-green { background: #169b75; }
        .bar-amber { background: #e59b25; }
        .two-col { border-collapse: separate; border-spacing: 12px 0; margin: 0 -12px; width: 100%; }
        .two-col > tbody > tr > td { vertical-align: top; width: 50%; }
        .rank { color: #1476b8; font-weight: bold; }
        .footer { bottom: -22px; color: #718096; font-size: 8px; left: 0; position: fixed; right: 0; text-align: right; }
        .empty { color: #718096; padding: 12px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $organizationName }} | Laporan CRM</div>
        <h1>Laporan Kinerja</h1>
        <div class="subtitle">Ringkasan leads, pendaftaran, target, sekolah, dan kinerja pengguna</div>
        <div class="meta">{{ $periodLabel }} &nbsp; | &nbsp; {{ $branchLabel }} &nbsp; | &nbsp; Dibuat {{ $generatedAt }}</div>
    </div>

    <table class="kpis">
        <tr>
            <td><div class="kpi-label">TOTAL LEADS</div><div class="kpi-value">{{ number_format($stats['leads'], 0, ',', '.') }}</div></td>
            <td><div class="kpi-label">PENDAFTARAN</div><div class="kpi-value">{{ number_format($stats['registrations'], 0, ',', '.') }}</div></td>
            <td><div class="kpi-label">KONVERSI</div><div class="kpi-value">{{ number_format($stats['conversion'], 1, ',', '.') }}%</div></td>
            <td><div class="kpi-label">OMZET TERVERIFIKASI</div><div class="kpi-value">Rp {{ number_format($stats['revenue'], 0, ',', '.') }}</div></td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <div class="section">
                    <h2>Leads berdasarkan program</h2>
                    <p class="hint">Distribusi minat program pada periode laporan</p>
                    <table class="data">
                        <thead><tr><th>Program</th><th>Klasifikasi</th><th class="num">Leads</th><th style="width:28%">Diagram</th></tr></thead>
                        <tbody>
                        @forelse($leadsByProgram as $program)
                            <tr>
                                <td>{{ $program->name }}</td><td>{{ $program->classification }}</td><td class="num">{{ number_format($program->total, 0, ',', '.') }}</td>
                                <td><div class="bar-track"><div class="bar" style="width:{{ $stats['leads'] > 0 ? min(100, $program->total / $stats['leads'] * 100) : 0 }}%"></div></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Belum ada leads program.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </td>
            <td>
                <div class="section">
                    <h2>Klasifikasi program</h2>
                    <p class="hint">Jumlah dan proporsi leads per kategori</p>
                    <table class="data">
                        <thead><tr><th>Kategori</th><th class="num">Leads</th><th style="width:42%">Diagram</th></tr></thead>
                        <tbody>
                        @forelse($programClassifications as $classification)
                            <tr>
                                <td>{{ $classification['name'] }}</td><td class="num">{{ number_format($classification['leads'], 0, ',', '.') }}</td>
                                <td><div class="bar-track"><div class="bar bar-purple" style="width:{{ $stats['leads'] > 0 ? min(100, $classification['leads'] / $stats['leads'] * 100) : 0 }}%"></div></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Belum ada klasifikasi program.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <div class="section">
                    <h2>Top 5 sekolah</h2>
                    <p class="hint">Sekolah dengan leads terbanyak</p>
                    <table class="data">
                        <thead><tr><th>Rank</th><th>Nama sekolah</th><th class="num">Leads</th><th style="width:32%">Diagram</th></tr></thead>
                        <tbody>
                        @forelse($topSchools as $school)
                            <tr>
                                <td class="rank">#{{ $loop->iteration }}</td><td>{{ $school->name }}</td><td class="num">{{ number_format($school->total, 0, ',', '.') }}</td>
                                <td><div class="bar-track"><div class="bar bar-amber" style="width:{{ $stats['leads'] > 0 ? min(100, $school->total / $stats['leads'] * 100) : 0 }}%"></div></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Belum ada data sekolah.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </td>
            <td>
                <div class="section">
                    <h2>Progress target cabang</h2>
                    <p class="hint">Realisasi periode ini dibanding target aktif dan selesai</p>
                    <table class="data">
                        <thead><tr><th>Cabang</th><th class="num">Progress omzet</th><th class="num">Progress siswa</th></tr></thead>
                        <tbody>
                        @forelse($branchesReport as $branch)
                            <tr>
                                <td>{{ $branch['name'] }}</td>
                                <td class="num">
                                    {{ number_format($branch['revenue_progress'], 1, ',', '.') }}%
                                    <div class="bar-track"><div class="bar bar-green" style="width:{{ min(100, $branch['revenue_progress']) }}%"></div></div>
                                </td>
                                <td class="num">
                                    {{ number_format($branch['student_progress'], 1, ',', '.') }}%
                                    <div class="bar-track"><div class="bar bar-purple" style="width:{{ min(100, $branch['student_progress']) }}%"></div></div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Belum ada target cabang pada periode ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2>Peringkat kinerja pengguna</h2>
        <p class="hint">Urutan berdasarkan progress target siswa, pendaftaran, lalu leads. Progress target omzet ditampilkan sebagai pembanding.</p>
        <table class="data">
            <thead><tr><th>Rank</th><th>Pengguna</th><th class="num">Leads</th><th class="num">Pendaftaran</th><th class="num">Omzet</th><th class="num">Target siswa</th><th>Progress siswa</th><th>Progress omzet</th></tr></thead>
            <tbody>
            @forelse($userPerformance as $user)
                <tr>
                    <td class="rank">#{{ $user['rank'] }}</td><td>{{ $user['name'] }}</td><td class="num">{{ number_format($user['leads'], 0, ',', '.') }}</td><td class="num">{{ number_format($user['registrations'], 0, ',', '.') }}</td><td class="num">Rp {{ number_format($user['revenue'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($user['target_students'], 0, ',', '.') }}</td>
                    <td>{{ number_format($user['student_progress'], 1, ',', '.') }}%<div class="bar-track"><div class="bar bar-purple" style="width:{{ min(100, $user['student_progress']) }}%"></div></div></td>
                    <td>{{ number_format($user['revenue_progress'], 1, ',', '.') }}%<div class="bar-track"><div class="bar bar-green" style="width:{{ min(100, $user['revenue_progress']) }}%"></div></div></td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Belum ada data kinerja pengguna.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Ringkasan pendaftaran per program</h2>
        <table class="data">
            <thead><tr><th>Program</th><th class="num">Pendaftaran</th><th class="num">Omzet terverifikasi</th></tr></thead>
            <tbody>
            @forelse($programsReport as $program)
                <tr><td>{{ $program['name'] }}</td><td class="num">{{ number_format($program['registrations'], 0, ',', '.') }}</td><td class="num">Rp {{ number_format($program['revenue'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="3" class="empty">Belum ada pendaftaran program.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">{{ $organizationName }} &nbsp; | &nbsp; Dokumen dibuat otomatis pada {{ $generatedAt }}</div>
</body>
</html>
