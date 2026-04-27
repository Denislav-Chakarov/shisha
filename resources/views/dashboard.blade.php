<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Табло</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="page-dashboard">
<div class="mobile-sidebar-backdrop" data-sidebar-backdrop></div>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">TOBACCO</div>
        <a class="nav-link active" href="{{ route('dashboard') }}"><span class="ic">□</span>Табло</a>
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
        <a class="nav-link" href="{{ route('reports') }}"><span class="ic">◫</span>Справки</a>
        <div class="menu-title">Служители</div>
        <div id="workersPresenceList" class="workers-list">
            @foreach (($workersPresence ?? []) as $worker)
                <div class="user-card worker-card {{ !empty($worker['online']) ? 'is-online' : 'is-offline' }}">
                    <div class="avatar">{{ strtoupper(substr((string) ($worker['username'] ?? 'U'), 0, 1)) }}</div>
                    <div>
                        <div class="who">
                            {{ $worker['username'] ?? 'Потребител' }}
                            @if (!empty($worker['is_current'])) <span class="you-tag">(Вие)</span> @endif
                        </div>
                        <div class="online"><span class="pulse"></span>{{ !empty($worker['online']) ? 'На линия' : 'Извън линия' }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="logout-box"><form method="POST" action="{{ route('logout') }}">@csrf <button type="submit" class="btn">Изход</button></form></div>
    </aside>

    <main class="main">
        <button class="mobile-nav-toggle" type="button" data-sidebar-toggle aria-label="Меню">☰</button>
        <div class="header">Табло</div>
        @if (session('status')) <div class="msg ok">{{ session('status') }}</div> @endif
        @if (session('error')) <div class="msg err">{{ session('error') }}</div> @endif
        @if ($errors->any()) <div class="msg err">{{ $errors->first() }}</div> @endif

        <section class="stats">
            <article class="stat"><div class="label">Свободни маси</div><div class="value">{{ $stats['free_tables'] }}/20</div></article>
            <article class="stat"><div class="label">Поръчки днес</div><div class="value">{{ $stats['today_orders'] }}</div></article>
            <article class="stat"><div class="label">Оборот днес</div><div class="value">€{{ number_format($stats['today_sales'], 2) }}</div></article>
            <article class="stat"><div class="label">Общо продукти</div><div class="value">{{ $stats['total_products'] }}</div></article>
        </section>

        <section class="content">
            <article class="panel">
                <h2>Преглед на масите</h2>
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
                        <a class="table-btn {{ $tableClass }} {{ $selectedTableId === (int) $table->id ? 'current' : '' }}" href="{{ route('dashboard', ['table' => $table->id]) }}">
                            <span class="table-no">{{ $table->table_number }}</span>
                            @if ((int) ($table->reservation_people_total ?? 0) > 0)
                                <span class="table-people">👥 {{ $table->reservation_people_total }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="legend">
                    <span><span class="dot g"></span>Свободна</span>
                    <span><span class="dot r"></span>Заета</span>
                    <span><span class="dot y"></span>Резервирана</span>
                    <span><span class="dot x"></span>Неактивна</span>
                </div>

                @php
                    $selectedTable = $tables->firstWhere('id', $selectedTableId);
                    $hasOpenOrderForSelectedTable = !empty($selectedOpenOrder);
                @endphp
                @if ($selectedTable)
                <div class="table-actions">
                    <div class="table-actions-top">
                        <h3>Действия за маса {{ $selectedTable->table_number }}</h3>
                        <div class="action-row">
                        <form method="POST" action="{{ route('dashboard.tables.status', $selectedTable->id) }}">@csrf <input type="hidden" name="status" value="available"><button class="chip" type="submit" @disabled($hasOpenOrderForSelectedTable) title="{{ $hasOpenOrderForSelectedTable ? 'Първо издайте сметка за активната поръчка.' : '' }}">Маркирай свободна</button></form>
                        <form method="POST" action="{{ route('dashboard.tables.status', $selectedTable->id) }}">@csrf <input type="hidden" name="status" value="occupied"><button class="chip" type="submit" @disabled($hasOpenOrderForSelectedTable) title="{{ $hasOpenOrderForSelectedTable ? 'Първо издайте сметка за активната поръчка.' : '' }}">Маркирай заета</button></form>
                        <form method="POST" action="{{ route('dashboard.tables.status', $selectedTable->id) }}">@csrf <input type="hidden" name="status" value="inactive"><button class="chip" type="submit" @disabled($hasOpenOrderForSelectedTable) title="{{ $hasOpenOrderForSelectedTable ? 'Първо издайте сметка за активната поръчка.' : '' }}">Маркирай неактивна</button></form>
                        </div>
                    </div>
                    @if ($hasOpenOrderForSelectedTable)
                        <div class="muted">Статус бутоните са заключени, докато поръчката не бъде приключена с "Издай сметка".</div>
                    @endif
                    <div class="muted">Създайте нова резервация (масата поддържа множество резервации).</div>
                    <form method="POST" action="{{ route('dashboard.tables.reservations.store', $selectedTable->id) }}" class="reserve-grid">
                        @csrf
                        <input type="text" name="reserved_from" class="datetime-picker" placeholder="От дата/час" required>
                        <input type="text" name="reserved_to" class="datetime-picker" placeholder="До дата/час" required>
                        <input type="number" name="reserved_people" min="1" max="30" placeholder="Брой хора" required>
                        <button class="chip" type="submit">Резервирай</button>
                    </form>

                    @if (($selectedReservations ?? collect())->isNotEmpty())
                        <div class="reservation-list">
                            @foreach ($selectedReservations as $reservation)
                                <div class="reservation-item">
                                    {{ \Illuminate\Support\Carbon::parse($reservation->starts_at)->format('d.m.Y H:i') }}
                                    - {{ \Illuminate\Support\Carbon::parse($reservation->ends_at)->format('d.m.Y H:i') }}
                                    | 👥 {{ $reservation->people_count }}
                                    @if (!empty($reservation->customer_name))
                                        | {{ $reservation->customer_name }}
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="ai-box">
                        <div class="muted" style="margin-bottom:6px;">
                            Добавяне на поръчка (пример: "2 cola, 1 musthave kiwi, 3 red grape")
                        </div>
                        <form method="POST" action="{{ route('dashboard.orders.ai') }}">
                            @csrf
                            <input type="hidden" name="store_table_id" value="{{ $selectedTable->id }}">
                            <textarea name="order_text" placeholder="Въведете свободен текст за поръчката..." required>{{ old('order_text', $aiDraftPrompt ?? '') }}</textarea>
                            <button class="chip" type="submit" style="margin-top:6px;">Добави към поръчка</button>
                        </form>
                        @if (!empty($lastAiPrompt))
                            <div class="muted ai-last-prompt">Последен въведен prompt: {{ $lastAiPrompt }}</div>
                        @endif
                    </div>

                </div>
                @endif
            </article>

            <aside class="panel">
                <h2>Последни поръчани продукти (Маса {{ $selectedTable->table_number ?? '-' }})</h2>
                @forelse ($recentProducts as $item)
                    <div class="product-row">
                        <div class="product-info">
                            <div class="product-name">{{ $item->product_name }}</div>
                            @if (!empty($item->meta_note))
                                <span class="hookah-flavors">{{ trim($item->meta_note, '() ') }}</span>
                            @endif
                            <small class="product-meta">{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('H:i') }} • €{{ number_format((float) $item->unit_price, 2) }}/бр</small>
                        </div>
                        <div class="qty">
                            <div>бр. {{ $item->quantity }}</div>
                            <small>€{{ number_format((float) $item->line_total, 2) }}</small>
                        </div>
                    </div>
                @empty
                    <div class="muted">Все още няма поръчани продукти.</div>
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
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/bg.js"></script>
<script>
    flatpickr(".datetime-picker", {
        enableTime: true,
        time_24hr: true,
        dateFormat: "Y-m-d H:i",
        locale: "bg",
        minuteIncrement: 5
    });

    (function () {
        const workersList = document.getElementById('workersPresenceList');
        if (!workersList) return;

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderWorkers(workers) {
            workersList.innerHTML = '';
            workers.forEach((worker) => {
                const username = escapeHtml(worker.username || 'Потребител');
                const firstLetter = username.charAt(0).toUpperCase() || 'U';
                const isOnline = !!worker.online;
                const currentTag = worker.is_current ? '<span class="you-tag">(Вие)</span>' : '';

                const wrapper = document.createElement('div');
                wrapper.className = `user-card worker-card ${isOnline ? 'is-online' : 'is-offline'}`;
                wrapper.innerHTML = `
                    <div class="avatar">${firstLetter}</div>
                    <div>
                        <div class="who">${username} ${currentTag}</div>
                        <div class="online"><span class="pulse"></span>${isOnline ? 'На линия' : 'Извън линия'}</div>
                    </div>
                `;
                workersList.appendChild(wrapper);
            });
        }

        async function refreshWorkersPresence() {
            try {
                const response = await fetch('{{ route('dashboard.workers_presence') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store',
                });
                if (!response.ok) return;
                const workers = await response.json();
                if (Array.isArray(workers)) {
                    renderWorkers(workers);
                }
            } catch (_) {
                // noop
            }
        }

        setInterval(refreshWorkersPresence, 10000);
    })();

    (function () {
        const btn = document.querySelector('[data-sidebar-toggle]');
        const backdrop = document.querySelector('[data-sidebar-backdrop]');
        if (!btn) return;
        btn.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
        backdrop?.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
    })();
</script>
</body>
</html>
