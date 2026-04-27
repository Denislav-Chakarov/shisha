<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Маси</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="page-tables">
<div class="mobile-sidebar-backdrop" data-sidebar-backdrop></div>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">TOBACCO</div>
        <a class="nav-link" href="{{ route('dashboard') }}"><span class="ic">□</span>Табло</a>
        <div class="menu-title">Управление</div>
        <a class="nav-link" href="{{ route('products') }}"><span class="ic">◈</span>Продукти</a>
        <a class="nav-link" href="{{ route('tables') }}"><span class="ic">▦</span>Маси</a>
        <a class="nav-link active" href="{{ route('tables') }}"><span class="ic">◍</span>Поръчки</a>
        <a class="nav-link" href="{{ route('products') }}"><span class="ic">◧</span>Наличности</a>
        <div class="menu-title">Анализи</div>
        <a class="nav-link" href="{{ route('reports') }}"><span class="ic">◫</span>Справки</a>
        <div class="user-card">
            <div class="avatar">{{ strtoupper(substr(auth()->user()->username ?? 'U', 0, 1)) }}</div>
            <div><div class="who">{{ auth()->user()->username ?? 'Потребител' }}</div><div class="online"><span class="pulse"></span>На линия</div></div>
        </div>
        <div class="logout-box"><form method="POST" action="{{ route('logout') }}">@csrf <button class="btn" type="submit">Изход</button></form></div>
    </aside>
    <main class="main">
        <button class="mobile-nav-toggle" type="button" data-sidebar-toggle aria-label="Меню">☰</button>
        <div class="header">Поръчки по маси</div>
        @if (session('status')) <div class="msg ok">{{ session('status') }}</div> @endif
        @if (session('error')) <div class="msg err">{{ session('error') }}</div> @endif
        @if ($errors->any()) <div class="msg err">{{ $errors->first() }}</div> @endif
        <section class="panel" style="margin-top:10px;">
            <h2>Изберете маса</h2>
            <div class="table-grid">
                @foreach ($tables as $table)
                    @php
                        $tableClass = (int) $table->open_orders > 0
                            ? 'occupied'
                            : (($table->reservation_count ?? 0) > 0
                                ? 'reserved'
                                : match($table->status ?? 'available') {
                                'inactive' => 'inactive',
                                'occupied' => 'occupied',
                                default => 'available',
                            });
                    @endphp
                    <a class="table-btn {{ $tableClass }} {{ $selectedTableId === (int) $table->id ? 'current' : '' }}" href="{{ route('tables', ['table' => $table->id]) }}">{{ $table->table_number }}</a>
                @endforeach
            </div>
            <div class="legend">
                <span><span class="dot g"></span>Свободна</span>
                <span><span class="dot r"></span>Заета</span>
                <span><span class="dot y"></span>Резервирана</span>
                <span><span class="dot x"></span>Неактивна</span>
            </div>
        </section>

        <section class="content">
            <article class="panel">
                @php
                    $selectedTable = $tables->firstWhere('id', $selectedTableId);
                    $hasOpenOrderForSelectedTable = !empty($selectedOpenOrder);
                @endphp
                @if ($selectedTable)
                    <div class="table-actions">
                        <div class="table-actions-top">
                            <h3>Поръчка за маса {{ $selectedTable->table_number }}</h3>
                            @if ($hasOpenOrderForSelectedTable)
                                <form method="POST" action="{{ route('dashboard.orders.close') }}">
                                    @csrf
                                    <input type="hidden" name="store_table_id" value="{{ $selectedTable->id }}">
                                    <button class="btn" type="submit" style="width:auto;padding:8px 12px;">Издай сметка</button>
                                </form>
                            @endif
                        </div>
                        <div class="muted">Въведете свободен текст за поръчка. Всички разпознати артикули се добавят отдолу.</div>
                        <div class="ai-box">
                            <form method="POST" action="{{ route('dashboard.orders.ai') }}">
                                @csrf
                                <input type="hidden" name="store_table_id" value="{{ $selectedTable->id }}">
                                <textarea name="order_text" placeholder="Пример: 2 коли, наргиле karm rocketman + blueberry" required>{{ old('order_text', $aiDraftPrompt ?? '') }}</textarea>
                                <button class="chip" type="submit" style="margin-top:6px;">Добави към поръчка</button>
                            </form>
                            @if (!empty($lastAiPrompt))
                                <div class="muted ai-last-prompt">Последен въведен prompt: {{ $lastAiPrompt }}</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="muted">Няма избрана маса.</div>
                @endif
            </article>

            <aside class="panel">
                <h2>Текущи артикули (Маса {{ $selectedTable->table_number ?? '-' }})</h2>
                @forelse (($selectedOrderItems ?? collect()) as $item)
                    <div class="product-row">
                        <div class="product-info">
                            <div class="product-name">{{ $item->product_name }}</div>
                            @if (!empty($item->meta_note))
                                <span class="hookah-flavors">{{ trim($item->meta_note, '() ') }}</span>
                            @endif
                            <small class="product-meta">{{ $item->quantity }} x €{{ number_format((float) $item->unit_price, 2) }}</small>
                        </div>
                        <div class="qty"><small>€{{ number_format((float) $item->line_total, 2) }}</small></div>
                    </div>
                @empty
                    <div class="muted">Все още няма артикули за избраната маса.</div>
                @endforelse
                <div class="right-order-summary">
                    <div class="order-total right-order-total">
                        <span>Общо до момента</span>
                        <strong>€{{ number_format((float) ($selectedOrderTotal ?? 0), 2) }}</strong>
                    </div>
                    @if (($selectedOrderItems ?? collect())->isNotEmpty() && isset($selectedTable))
                        <form method="POST" action="{{ route('dashboard.orders.close') }}" class="right-order-close">
                            @csrf
                            <input type="hidden" name="store_table_id" value="{{ $selectedTable->id }}">
                            <button class="btn" type="submit">Издай сметка</button>
                        </form>
                    @endif
                </div>
            </aside>
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
