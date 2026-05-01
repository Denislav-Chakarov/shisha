<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вземи поръчка</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        .take-order-table-grid .table-btn {
            aspect-ratio: auto !important;
            height: 48px !important;
            min-height: 48px !important;
            font-size: 15px;
            padding: 8px 10px;
            justify-content: center;
        }

        .take-order-prompt textarea {
            min-height: 320px;
        }
    </style>
</head>
<body class="page-tables">
<div class="mobile-sidebar-backdrop" data-sidebar-backdrop></div>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">TOBACCO</div>
        <a class="nav-link" href="{{ route('dashboard') }}"><span class="ic">□</span>Табло</a>
        <div class="menu-title">Управление</div>
        <a class="nav-link" href="{{ route('products') }}"><span class="ic">◈</span>Продукти</a>
        <a class="nav-link active" href="{{ route('take_order') }}"><span class="ic">◍</span>Вземи поръчка</a>
        <a class="nav-link" href="{{ route('recipes') }}"><span class="ic">◌</span>Рецепти</a>
        <a class="nav-link" href="{{ route('inventory') }}"><span class="ic">◧</span>Наличности</a>
        <a class="nav-link" href="{{ route('deliveries') }}"><span class="ic">◨</span>Зареждания</a>
        <a class="nav-link" href="{{ route('invoice_import') }}"><span class="ic">◇</span>Импорт фактура</a>
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
        <div class="logout-box"><form method="POST" action="{{ route('logout') }}">@csrf <button class="btn" type="submit">Изход</button></form></div>
    </aside>
    <main class="main">
        <button class="mobile-nav-toggle" type="button" data-sidebar-toggle aria-label="Меню">☰</button>
        <div class="header">Вземи поръчка</div>
        @if (session('status')) <div class="msg ok">{{ session('status') }}</div> @endif
        @if (session('error')) <div class="msg err">{{ session('error') }}</div> @endif
        @if ($errors->any()) <div class="msg err">{{ $errors->first() }}</div> @endif

        <section class="panel" style="margin-top:10px;">
            <h2>Изберете маса</h2>
            <div class="table-grid take-order-table-grid">
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
                    <a class="table-btn {{ $tableClass }} {{ $selectedTableId === (int) $table->id ? 'current' : '' }}" href="{{ route('take_order', ['table' => $table->id]) }}">{{ $table->table_number }}</a>
                @endforeach
            </div>
        </section>

        <section class="content">
            <article class="panel">
                @php $selectedTable = $tables->firstWhere('id', $selectedTableId); @endphp
                @if ($selectedTable)
                    <h3>Поръчка за маса {{ $selectedTable->table_number }}</h3>
                    <div class="ai-box take-order-prompt">
                        <div class="muted" style="margin-bottom:6px;">Бърз prompt (поддържа български):</div>
                        <form method="POST" action="{{ route('dashboard.orders.ai') }}">
                            @csrf
                            <input type="hidden" name="store_table_id" value="{{ $selectedTable->id }}">
                            <textarea name="order_text" placeholder="Пример: 2 коли, 1 фанта, едно наргиле с blueberry + kiwi" required>{{ old('order_text', $aiDraftPrompt ?? '') }}</textarea>
                            <button class="chip" type="submit" style="margin-top:6px;">Добави с prompt</button>
                        </form>
                    </div>

                    <div class="muted" style="margin:10px 0 6px;">Ръчно добавяне:</div>
                    <form method="POST" action="{{ route('dashboard.orders.items.store') }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        @csrf
                        <input type="hidden" name="store_table_id" value="{{ $selectedTable->id }}">
                        <select name="product_id" required style="min-width:260px;">
                            <option value="">Изберете продукт</option>
                            @foreach ($products as $product)
                                @if ((int) ($product->is_active ?? 1) === 1)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->brand_name }})</option>
                                @endif
                            @endforeach
                        </select>
                        <input type="number" name="quantity" min="1" max="100" value="1" required style="width:80px;">
                        <button class="chip" type="submit">Добави ръчно</button>
                    </form>
                @endif
            </article>

            <aside class="panel">
                <h2>Текущи артикули</h2>
                @forelse (($selectedOrderItems ?? collect()) as $item)
                    <div class="product-row">
                        <div class="product-info">
                            <div class="product-name">{{ $item->product_name }}</div>
                            <small class="product-meta">Статус: {{ ($item->item_status ?? 'ordered') === 'served' ? 'Сервирано' : 'Поръчано' }}</small>
                        </div>
                        <div class="qty">
                            <div>бр. {{ $item->quantity }}</div>
                            <small>€{{ number_format((float) $item->line_total, 2) }}</small>
                        </div>
                    </div>
                @empty
                    <div class="muted">Няма артикули за избраната маса.</div>
                @endforelse
            </aside>
        </section>
    </main>
</div>
<script>
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
        btn?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
        backdrop?.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
    })();
</script>
</body>
</html>

