<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Справки</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="page-reports">
<div class="mobile-sidebar-backdrop" data-sidebar-backdrop></div>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">TOBACCO</div>
        <a class="nav-link" href="{{ route('dashboard') }}"><span class="ic">□</span>Табло</a>
        <div class="menu-title">Управление</div>
        <a class="nav-link" href="{{ route('products') }}"><span class="ic">◈</span>Продукти</a>
        <a class="nav-link" href="{{ route('tables') }}"><span class="ic">▦</span>Маси</a>
        <a class="nav-link" href="{{ route('tables') }}"><span class="ic">◍</span>Поръчки</a>
        <a class="nav-link" href="{{ route('products') }}"><span class="ic">◧</span>Наличности</a>
        <div class="menu-title">Настройки</div>
        <a class="nav-link" href="#"><span class="ic">◉</span>Потребители</a>
        <a class="nav-link" href="#"><span class="ic">◌</span>Категории</a>
        <a class="nav-link" href="#"><span class="ic">◇</span>Настройки</a>
        <div class="menu-title">Анализи</div>
        <a class="nav-link active" href="{{ route('reports') }}"><span class="ic">◫</span>Справки</a>
        <div class="user-card">
            <div class="avatar">{{ strtoupper(substr(auth()->user()->username ?? 'U', 0, 1)) }}</div>
            <div><div class="who">{{ auth()->user()->username ?? 'Потребител' }}</div><div class="online"><span class="pulse"></span>На линия</div></div>
        </div>
        <div class="logout-box"><form method="POST" action="{{ route('logout') }}">@csrf <button class="btn" type="submit">Изход</button></form></div>
    </aside>

    <main class="main">
        <button class="mobile-nav-toggle" type="button" data-sidebar-toggle aria-label="Меню">☰</button>
        <div class="header">Справки за приключени поръчки</div>
        @if (session('status')) <div class="msg ok">{{ session('status') }}</div> @endif
        @if (session('error')) <div class="msg err">{{ session('error') }}</div> @endif
        @if ($errors->any()) <div class="msg err">{{ $errors->first() }}</div> @endif

        <section class="panel" style="margin-top:10px;">
            <h2>Филтри</h2>
            <form method="GET" action="{{ route('reports') }}" class="report-filters">
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                <select name="table_id">
                    <option value="">Всички маси</option>
                    @foreach ($tables as $table)
                        <option value="{{ $table->id }}" @selected((int) ($filters['table_id'] ?? 0) === (int) $table->id)>Маса {{ $table->table_number }}</option>
                    @endforeach
                </select>
                <button class="btn" type="submit">Покажи</button>
            </form>
        </section>

        <section class="stats">
            <article class="stat"><div class="label">Приключени поръчки</div><div class="value">{{ $summary['orders_count'] }}</div></article>
            <article class="stat"><div class="label">Общ оборот</div><div class="value">€{{ number_format((float) $summary['revenue_total'], 2) }}</div></article>
        </section>

        <section class="content">
            <article class="panel">
                <h2>По дни</h2>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead><tr><th>Дата</th><th>Поръчки</th><th>Оборот</th></tr></thead>
                        <tbody>
                        @forelse ($byDay as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->report_day)->format('d.m.Y') }}</td>
                                <td>{{ $row->orders_count }}</td>
                                <td>€{{ number_format((float) $row->revenue_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Няма данни за периода.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="panel">
                <h2>По маси</h2>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead><tr><th>Маса</th><th>Поръчки</th><th>Оборот</th></tr></thead>
                        <tbody>
                        @forelse ($byTable as $row)
                            <tr>
                                <td>Маса {{ $row->table_number }}</td>
                                <td>{{ $row->orders_count }}</td>
                                <td>€{{ number_format((float) $row->revenue_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Няма данни за периода.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </aside>
        </section>

        <section class="panel" style="margin-top:10px;">
            <h2>Детайлни приключени поръчки</h2>
            <div class="report-table-wrap">
                <table class="report-table">
                    <thead><tr><th>#</th><th>Маса</th><th>Артикули</th><th>Общо</th><th>Приключена</th></tr></thead>
                    <tbody>
                    @forelse ($ordersDetailed as $row)
                        <tr>
                            <td>{{ $row->order_id }}</td>
                            <td>Маса {{ $row->table_number }}</td>
                            <td>{{ $row->items_count }}</td>
                            <td>€{{ number_format((float) $row->total_amount, 2) }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->closed_at)->format('d.m.Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Няма приключени поръчки за периода.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script>
    (function () {
        const btn = document.querySelector('[data-sidebar-toggle]');
        const backdrop = document.querySelector('[data-sidebar-backdrop]');
        btn?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
        backdrop?.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
    })();
</script>
</body>
</html>
