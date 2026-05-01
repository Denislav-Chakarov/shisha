<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Продукти</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="page-products">
@php
    $filters = $filters ?? ['q' => '', 'category' => 'all', 'sort' => 'name_asc', 'view' => 'list'];
@endphp
<div class="mobile-sidebar-backdrop" data-sidebar-backdrop></div>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">TOBACCO</div>
        <a class="nav-link" href="{{ route('dashboard') }}"><span class="ic">□</span>Табло</a>
        <div class="menu-title">Управление</div>
        <a class="nav-link {{ request()->routeIs('products') ? 'active' : '' }}" href="{{ route('products') }}"><span class="ic">◈</span>Продукти</a>
        <a class="nav-link" href="{{ route('take_order') }}"><span class="ic">◍</span>Вземи поръчка</a>
        <a class="nav-link" href="{{ route('recipes') }}"><span class="ic">◌</span>Рецепти</a>
        <a class="nav-link {{ request()->routeIs('inventory') ? 'active' : '' }}" href="{{ route('inventory') }}"><span class="ic">◧</span>Наличности</a>
        <a class="nav-link {{ request()->routeIs('deliveries') ? 'active' : '' }}" href="{{ route('deliveries') }}"><span class="ic">◨</span>Зареждания</a>
        <a class="nav-link" href="{{ route('invoice_import') }}"><span class="ic">◇</span>Импорт фактура</a>
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
        <div class="header">Продукти и наличности</div>
        @if (session('status')) <div class="msg ok">{{ session('status') }}</div> @endif
        @if (session('error')) <div class="msg err">{{ session('error') }}</div> @endif
        @if ($errors->any()) <div class="msg err">{{ $errors->first() }}</div> @endif

        @php
            $activeTab = $tab ?? 'products';
            $baseQuery = request()->query();
        @endphp

        @if (request()->routeIs('products'))
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <a class="nav-link @if($activeTab === 'products') active @endif"
                       href="{{ route('products', array_merge($baseQuery, ['tab' => 'products'])) }}">Продукти</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if($activeTab === 'brands') active @endif"
                       href="{{ route('products', array_merge($baseQuery, ['tab' => 'brands'])) }}">Брандове</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if($activeTab === 'categories') active @endif"
                       href="{{ route('products', array_merge($baseQuery, ['tab' => 'categories'])) }}">Категории</a>
                </li>
            </ul>
        @endif

        @if ($activeTab === 'deliveries')
            @include('catalog._deliveries_tab')
        @elseif ($activeTab === 'inventory')
            @include('catalog._inventory_tab')
        @elseif ($activeTab === 'categories')
            @include('catalog._categories_tab')
        @elseif ($activeTab === 'brands')
            @include('catalog._brands_tab')
        @else
            @include('catalog._products_tab')
        @endif
    </main>
</div>

<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Потвърждение за изтриване</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="deleteModalText" class="mb-0">Сигурни ли сте, че искате да изтриете продукта?</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отказ</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Изтрий</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const sidebarBtn = document.querySelector('[data-sidebar-toggle]');
        const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
        sidebarBtn?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
        sidebarBackdrop?.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
    })();
</script>
</body>
</html>
