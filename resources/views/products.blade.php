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
<div class="mobile-sidebar-backdrop" data-sidebar-backdrop></div>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">TOBACCO</div>
        <a class="nav-link" href="{{ route('dashboard') }}"><span class="ic">□</span>Табло</a>
        <div class="menu-title">Управление</div>
        <a class="nav-link active" href="{{ route('products') }}"><span class="ic">◈</span>Продукти</a>
        <div class="subtype-links">
            <a class="{{ ($filters['category'] ?? 'all') === 'tobacco' ? 'active' : '' }}" href="{{ route('products', ['category' => 'tobacco', 'q' => $filters['q'] ?? '', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => $filters['view'] ?? 'list']) }}">Тютюн</a>
            <a class="{{ ($filters['category'] ?? 'all') === 'drink' ? 'active' : '' }}" href="{{ route('products', ['category' => 'drink', 'q' => $filters['q'] ?? '', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => $filters['view'] ?? 'list']) }}">Напитки</a>
            <a class="{{ ($filters['category'] ?? 'all') === 'hookah' ? 'active' : '' }}" href="{{ route('products', ['category' => 'hookah', 'q' => $filters['q'] ?? '', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => $filters['view'] ?? 'list']) }}">Наргилета</a>
        </div>
        <a class="nav-link" href="{{ route('tables') }}"><span class="ic">▦</span>Маси</a>
        <a class="nav-link" href="{{ route('tables') }}"><span class="ic">◍</span>Поръчки</a>
        <a class="nav-link active" href="{{ route('products') }}"><span class="ic">◧</span>Наличности</a>
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

        <section class="grid product-create-grid">
            <article class="panel">
                <h2>Добавяне на продукт</h2>
                <form method="POST" action="{{ route('dashboard.products.store') }}" enctype="multipart/form-data">
                    @csrf
                    <select name="category" required>
                        <option value="tobacco">Тютюн</option>
                        <option value="drink">Напитка</option>
                        <option value="hookah">Наргиле</option>
                    </select>
                    <select id="createBrandSelect" name="brand_id" required>
                        <option value="">Изберете бранд</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" data-category="{{ $brand->category ?? 'tobacco' }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <input id="createProductName" type="text" name="name" placeholder="Име на продукт" required>
                    <input type="hidden" name="selected_image_path" id="selectedImagePath">
                    <div id="imageSuggestions" class="image-suggestions" style="display:none;"></div>
                    <div id="createNontobaccoPriceFields">
                        <input type="number" step="0.01" min="0" name="purchase_price" id="createPurchasePrice" placeholder="Пазарна цена (€)">
                        <input type="number" step="0.01" min="0" name="sale_price" id="createSalePrice" placeholder="Продажна цена (€) — по избор">
                    </div>
                    <p id="createTobaccoHint" class="muted" style="display:none;margin:6px 0 0;">За тютюн: наличността се смята автоматично от кутиите по разфасовка (след „Редактирай“).</p>
                    <div id="createStockWrap">
                        <input type="number" min="0" name="stock_quantity" id="createStockQty" placeholder="Наличност" required>
                    </div>
                    <input type="text" name="unit" value="бр" placeholder="Мерна единица (за напитки)">
                    <label for="image" class="file-picker">Качи снимка (JPG, PNG, WEBP)</label>
                    <input id="image" class="file-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                    <div id="fileName" class="file-name">Няма избран файл</div>
                    <button class="btn" type="submit">Запази продукт</button>
                </form>
            </article>

            <article class="panel">
                <h2>Добавяне на бранд</h2>
                <form method="POST" action="{{ route('dashboard.brands.store') }}">
                    @csrf
                    <input type="text" name="name" placeholder="Име на бранд" required maxlength="255">
                    <select name="category" required>
                        <option value="tobacco">Тютюн</option>
                        <option value="drink">Напитка</option>
                        <option value="hookah">Наргиле</option>
                    </select>
                    <button class="btn" type="submit">Запази бранд</button>
                </form>
            </article>
        </section>

        <form id="filtersForm" class="filters" method="GET" action="{{ route('products') }}">
            <input id="filterQ" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Търсене по име, бранд, вкус...">
            <select id="filterCategory" name="category">
                <option value="all" @selected(($filters['category'] ?? 'all') === 'all')>Всички типове</option>
                <option value="tobacco" @selected(($filters['category'] ?? 'all') === 'tobacco')>Тютюн</option>
                <option value="drink" @selected(($filters['category'] ?? 'all') === 'drink')>Напитки</option>
                <option value="hookah" @selected(($filters['category'] ?? 'all') === 'hookah')>Наргилета</option>
            </select>
            <select id="filterSort" name="sort">
                <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>По име</option>
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Продажна цена възходящо</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Продажна цена низходящо</option>
                <option value="stock_asc" @selected(($filters['sort'] ?? '') === 'stock_asc')>Наличност възходящо</option>
                <option value="stock_desc" @selected(($filters['sort'] ?? '') === 'stock_desc')>Наличност низходящо</option>
            </select>
            <input type="hidden" name="view" value="{{ $filters['view'] ?? 'list' }}">
            <div class="view-toggle">
                <a class="{{ ($filters['view'] ?? 'list') === 'list' ? 'active' : '' }}" href="{{ route('products', ['q' => $filters['q'] ?? '', 'category' => $filters['category'] ?? 'all', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => 'list']) }}">Вертикално</a>
                <a class="{{ ($filters['view'] ?? 'list') === 'grid' ? 'active' : '' }}" href="{{ route('products', ['q' => $filters['q'] ?? '', 'category' => $filters['category'] ?? 'all', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => 'grid']) }}">Карти</a>
            </div>
        </form>

        @if (($filters['view'] ?? 'list') === 'grid')
            <section class="products-grid">
                @forelse ($filteredProducts as $product)
                    @php
                        $imageUrl = null;
                        if (!empty($product->image_path)) {
                            $imageUrl = str_starts_with($product->image_path, 'http')
                                ? $product->image_path
                                : (str_starts_with($product->image_path, 'storage/') || str_starts_with($product->image_path, 'assets/')
                                    ? asset($product->image_path)
                                    : asset('storage/' . $product->image_path));
                        }
                    @endphp
                    <article class="card">
                        <div class="img-wrap">
                            @if ($imageUrl) <img src="{{ $imageUrl }}" alt="{{ $product->name }}"> @else <div class="img-placeholder">Няма снимка</div> @endif
                        </div>
                        <div class="card-body">
                            <div class="title">{{ $product->name }}</div>
                            <div class="meta">{{ $product->brand_name }} • {{ $product->category === 'drink' ? 'Напитка' : ($product->category === 'hookah' ? 'Наргиле' : 'Тютюн') }}</div>
                            @php
                                $tpb = $tobaccoPackPurchasesByProduct ?? collect();
                                $gridPurch = $tpb->get($product->id, collect());
                                $gridLatest = $gridPurch->first();
                            @endphp
                            @if ($product->category === 'tobacco' && $gridLatest)
                                <div class="row muted" style="font-size:0.85em;">
                                    Последно зареждане: {{ \Illuminate\Support\Carbon::parse($gridLatest->restocked_at)->format('d.m.Y') }}
                                    · {{ (int) $gridLatest->pack_grams }}g × {{ (int) $gridLatest->boxes_count }} кут.
                                    · €{{ number_format((float) $gridLatest->purchase_price_per_box, 2) }}/кут.
                                </div>
                            @endif
                            <div class="row">
                                <span>
                                    @if ($product->category === 'tobacco')
                                        цени в зарежданията
                                    @else
                                        Прод. €{{ number_format($product->price, 2) }} · Паз. €{{ number_format($product->purchase_price ?? 0, 2) }}
                                    @endif
                                </span>
                                <span>{{ $product->stock_quantity }} {{ $product->unit }}</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <article class="panel">Няма продукти за избраните филтри.</article>
                @endforelse
            </section>
        @else
            <section class="products-list">
                @forelse ($filteredProducts as $product)
                    @php
                        $imageUrl = null;
                        if (!empty($product->image_path)) {
                            $imageUrl = str_starts_with($product->image_path, 'http')
                                ? $product->image_path
                                : (str_starts_with($product->image_path, 'storage/') || str_starts_with($product->image_path, 'assets/')
                                    ? asset($product->image_path)
                                    : asset('storage/' . $product->image_path));
                        }
                    @endphp
                    <article class="list-row" data-product-row>
                        <div class="list-thumb">
                            @if ($imageUrl) <img src="{{ $imageUrl }}" alt="{{ $product->name }}"> @else <div class="img-placeholder">Без снимка</div> @endif
                        </div>
                        <div class="info">
                            <strong>{{ $product->name }}</strong>
                            <span class="meta">{{ $product->brand_name }} • {{ $product->category === 'drink' ? 'Напитка' : ($product->category === 'hookah' ? 'Наргиле' : 'Тютюн') }}</span>
                            @php
                                $tpbList = $tobaccoPackPurchasesByProduct ?? collect();
                                $listPurch = $tpbList->get($product->id, collect());
                                $listLatest = $listPurch->first();
                            @endphp
                            @if ($product->category === 'tobacco' && $listLatest)
                                <span class="meta" style="display:block;margin-top:4px;">
                                    Последно: {{ \Illuminate\Support\Carbon::parse($listLatest->restocked_at)->format('d.m.Y') }}
                                    · {{ (int) $listLatest->pack_grams }}g × {{ (int) $listLatest->boxes_count }} кут.
                                    · €{{ number_format((float) $listLatest->purchase_price_per_box, 2) }}/кут.
                                </span>
                            @endif
                        </div>
                        @if ($product->category === 'tobacco')
                            <div class="kpi"><div class="label">Цени</div><div class="value">—</div></div>
                            <div class="kpi"><div class="label">Зареждане</div><div class="value">{{ $listLatest ? \Illuminate\Support\Carbon::parse($listLatest->restocked_at)->format('d.m') : '—' }}</div></div>
                        @else
                            <div class="kpi"><div class="label">Продажна</div><div class="value">€{{ number_format($product->price, 2) }}</div></div>
                            <div class="kpi"><div class="label">Пазарна</div><div class="value">€{{ number_format($product->purchase_price ?? 0, 2) }}</div></div>
                        @endif
                        <div class="kpi"><div class="label">Наличност</div><div class="value">{{ $product->stock_quantity }} {{ $product->unit }}</div></div>
                        <div class="actions">
                            <button type="button" class="btn-secondary" data-edit-toggle>Редактирай</button>
                            <form method="POST" action="{{ route('dashboard.products.delete', $product->id) }}">
                                @csrf @method('DELETE')
                                <button class="btn-danger" type="button" data-delete-btn data-product-name="{{ $product->name }}">Изтрий</button>
                            </form>
                        </div>
                        <div class="edit-panel" style="grid-column:1 / -1;">
                            @php
                                $tpbEdit = $tobaccoPackPurchasesByProduct ?? collect();
                                $editPurchRows = $tpbEdit->get($product->id, collect());
                                $tinvEdit = $tobaccoPackInventoryByProduct ?? collect();
                                $editInvRows = $tinvEdit->get($product->id, collect());
                            @endphp
                            <form method="POST" action="{{ route('dashboard.products.update', $product->id) }}" enctype="multipart/form-data">
                                @csrf @method('PUT')
                                <div class="edit-grid">
                                    <select name="category" required>
                                        <option value="tobacco" @selected($product->category === 'tobacco')>Тютюн</option>
                                        <option value="drink" @selected($product->category === 'drink')>Напитка</option>
                                        <option value="hookah" @selected($product->category === 'hookah')>Наргиле</option>
                                    </select>
                                    <select name="brand_id" required>
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}" data-category="{{ $brand->category ?? 'tobacco' }}" @selected($product->brand_id == $brand->id)>{{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="name" value="{{ $product->name }}" required>
                                    <div class="edit-nontobacco-prices" data-edit-nontobacco-prices style="{{ $product->category === 'tobacco' ? 'display:none;' : '' }}">
                                        <input type="number" step="0.01" min="0" name="purchase_price" value="{{ $product->purchase_price ?? 0 }}" placeholder="Пазарна цена (€)">
                                        <input type="number" step="0.01" min="0" name="sale_price" value="{{ $product->price }}" placeholder="Продажна цена (€)">
                                    </div>
                                    <div data-edit-stock-fields style="{{ $product->category === 'tobacco' ? 'display:none;' : '' }}">
                                        <input type="number" min="0" name="stock_quantity" value="{{ $product->stock_quantity }}" required placeholder="Наличност">
                                        <input type="number" min="0" name="restock_add" value="0" placeholder="Добави към наличност (+)">
                                    </div>
                                    <input type="text" name="unit" value="{{ $product->unit }}">
                                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                                    <label><input type="checkbox" name="is_active" value="1" @checked($product->is_active)> Активен</label>
                                    <button class="btn" type="submit">Запази промени</button>
                                </div>
                            </form>
                            @if ($product->category === 'tobacco')
                                <p class="muted small mt-2 mb-0"><strong>Общо грамове (авто):</strong> {{ (int) $product->stock_quantity }} g — сума от разфасовка × налични кутии.</p>

                                <details class="tobacco-inv-menu mt-3" open style="border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:10px;">
                                    <summary class="h6 mb-0" style="cursor:pointer;">Наличност по разфасовки (кутии)</summary>
                                    <p class="muted small mt-2 mb-2">Промяна на кутиите преизчислява общия грамаж. Нови разфасовки се появяват автоматично през „Доставки“.</p>
                                    @if ($editInvRows->isEmpty())
                                        <p class="muted small">Няма редове. Добави доставка, за да се появят разфасовки.</p>
                                    @else
                                        <div class="table-responsive mb-2">
                                            <table class="table table-sm table-dark table-bordered align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Разфасовка</th>
                                                        <th>Кутии налични</th>
                                                        <th>Грамове (ред)</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($editInvRows as $inv)
                                                        <tr>
                                                            <td>{{ (int) $inv->pack_grams }} g</td>
                                                            <td>
                                                                <form method="POST" action="{{ route('dashboard.products.tobacco_pack_inventory.update', [$product->id, $inv->id]) }}" class="d-flex gap-1 align-items-center flex-wrap">
                                                                    @csrf @method('PUT')
                                                                    <input type="number" name="boxes_on_hand" class="form-control form-control-sm" style="width:90px;" min="0" step="1" value="{{ (int) $inv->boxes_on_hand }}" required>
                                                                    <button type="submit" class="btn btn-sm btn-outline-light py-0">OK</button>
                                                                </form>
                                                            </td>
                                                            <td>{{ (int) $inv->pack_grams * (int) $inv->boxes_on_hand }} g</td>
                                                            <td></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </details>

                                <details class="tobacco-deliveries-menu mt-3" style="border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:10px;">
                                    <summary class="h6 mb-0" style="cursor:pointer;">Доставки / зареждания</summary>
                                    <p class="muted small mt-2 mb-2">Пазарна цена за кутия; при запис кутиите към съответната разфасовка се увеличават. Редакцията коригира и наличните кутии.</p>
                                    @if ($editPurchRows->isEmpty())
                                        <p class="muted small mb-2">Няма записани доставки.</p>
                                    @else
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm table-dark table-bordered align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Дата</th>
                                                        <th>Разфасовка</th>
                                                        <th>Кутии (доставено)</th>
                                                        <th>€/кутия</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($editPurchRows as $pur)
                                                        <tr>
                                                            <td colspan="5" class="p-2 bg-black bg-opacity-25">
                                                                <form method="POST" action="{{ route('dashboard.products.tobacco_pack_purchases.update', [$product->id, $pur->id]) }}" class="row g-2 align-items-end">
                                                                    @csrf @method('PUT')
                                                                    <div class="col-auto">
                                                                        <label class="form-label small mb-0">Дата</label>
                                                                        <input type="date" name="restocked_at" class="form-control form-control-sm" required value="{{ \Illuminate\Support\Carbon::parse($pur->restocked_at)->format('Y-m-d') }}">
                                                                    </div>
                                                                    <div class="col-auto">
                                                                        <label class="form-label small mb-0">g</label>
                                                                        <input type="number" name="pack_grams" class="form-control form-control-sm" min="1" step="1" required value="{{ (int) $pur->pack_grams }}">
                                                                    </div>
                                                                    <div class="col-auto">
                                                                        <label class="form-label small mb-0">Кутии</label>
                                                                        <input type="number" name="boxes_count" class="form-control form-control-sm" min="1" step="1" required value="{{ (int) $pur->boxes_count }}">
                                                                    </div>
                                                                    <div class="col-auto">
                                                                        <label class="form-label small mb-0">€/кутия</label>
                                                                        <input type="number" name="purchase_price_per_box" class="form-control form-control-sm" min="0" step="0.01" required value="{{ (float) $pur->purchase_price_per_box }}">
                                                                    </div>
                                                                    <div class="col-auto">
                                                                        <button type="submit" class="btn btn-sm btn-primary">Запази</button>
                                                                    </div>
                                                                </form>
                                                                <form method="POST" action="{{ route('dashboard.products.tobacco_pack_purchases.destroy', [$product->id, $pur->id]) }}" class="d-inline mt-1" onsubmit="return confirm('Изтриване на доставката?');">
                                                                    @csrf @method('DELETE')
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0">Изтрий доставка</button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                    <form method="POST" action="{{ route('dashboard.products.tobacco_pack_purchases.store', $product->id) }}" class="d-flex flex-wrap gap-2 align-items-end">
                                        @csrf
                                        <div>
                                            <label class="form-label small mb-0">Дата</label>
                                            <input type="date" name="restocked_at" class="form-control form-control-sm" required value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                        <div>
                                            <label class="form-label small mb-0">Разфасовка (g)</label>
                                            <input type="number" name="pack_grams" class="form-control form-control-sm" min="1" step="1" required placeholder="50">
                                        </div>
                                        <div>
                                            <label class="form-label small mb-0">Кутии</label>
                                            <input type="number" name="boxes_count" class="form-control form-control-sm" min="1" step="1" required placeholder="10">
                                        </div>
                                        <div>
                                            <label class="form-label small mb-0">€/кутия</label>
                                            <input type="number" name="purchase_price_per_box" class="form-control form-control-sm" min="0" step="0.01" required placeholder="12.50">
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">Нова доставка</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </article>
                @empty
                    <article class="panel">Няма продукти за избраните филтри.</article>
                @endforelse
            </section>
        @endif

        @if ($filteredProducts instanceof \Illuminate\Pagination\LengthAwarePaginator && $filteredProducts->lastPage() > 1)
            <nav class="pagination">
                @if ($filteredProducts->onFirstPage())
                    <span>Предишна</span>
                @else
                    <a href="{{ $filteredProducts->previousPageUrl() }}">Предишна</a>
                @endif
                @for ($page = 1; $page <= $filteredProducts->lastPage(); $page++)
                    @if ($page == $filteredProducts->currentPage())
                        <span class="active">{{ $page }}</span>
                    @else
                        <a href="{{ $filteredProducts->url($page) }}">{{ $page }}</a>
                    @endif
                @endfor
                @if ($filteredProducts->hasMorePages())
                    <a href="{{ $filteredProducts->nextPageUrl() }}">Следваща</a>
                @else
                    <span>Следваща</span>
                @endif
            </nav>
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
        const form = document.getElementById('filtersForm');
        const q = document.getElementById('filterQ');
        const category = document.getElementById('filterCategory');
        const sort = document.getElementById('filterSort');
        const imageInput = document.getElementById('image');
        const fileName = document.getElementById('fileName');
        const createCategory = document.querySelector('form[action*="dashboard/products"] select[name="category"]');
        const createBrandSelect = document.getElementById('createBrandSelect');
        const productNameInput = document.getElementById('createProductName');
        const selectedImagePathInput = document.getElementById('selectedImagePath');
        const imageSuggestions = document.getElementById('imageSuggestions');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
        const deleteModalText = document.getElementById('deleteModalText');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        let deleteForm = null;
        let timeoutId = null;
        let suggestTimeout = null;

        function submitDebounced() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => form.submit(), 250);
        }

        function filterBrandOptions(brandSelect, categoryValue) {
            if (!brandSelect) return;
            const options = Array.from(brandSelect.querySelectorAll('option'));
            options.forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }
                const optionCategory = option.getAttribute('data-category') || 'tobacco';
                option.hidden = optionCategory !== categoryValue;
            });
            const selectedOption = brandSelect.options[brandSelect.selectedIndex];
            if (selectedOption && selectedOption.hidden) {
                brandSelect.value = '';
            }
        }

        function toggleProductFields(prefix = '') {
            const categorySelect = document.querySelector(`${prefix}select[name="category"]`);
            const saleInput = document.querySelector(`${prefix}input[name="sale_price"]`);
            const unitInput = document.querySelector(`${prefix}input[name="unit"]`);
            const nameInput = document.querySelector(`${prefix}input[name="name"]`);
            if (!categorySelect || !saleInput || !unitInput) return;
            const isTobacco = categorySelect.value === 'tobacco';
            const isHookah = categorySelect.value === 'hookah';
            unitInput.disabled = isTobacco;
            if (isTobacco) {
                unitInput.value = 'g';
            } else if (unitInput.value === 'g') {
                unitInput.value = 'бр';
            }

            if (nameInput) {
                nameInput.disabled = isHookah;
                nameInput.required = !isHookah;
                nameInput.style.display = isHookah ? 'none' : '';
                if (isHookah) {
                    nameInput.value = '';
                }
            }
            syncCreateTobaccoUi();
        }

        function syncCreateTobaccoUi() {
            const priceWrap = document.getElementById('createNontobaccoPriceFields');
            const hint = document.getElementById('createTobaccoHint');
            const pr = document.getElementById('createPurchasePrice');
            const sale = document.getElementById('createSalePrice');
            const stockWrap = document.getElementById('createStockWrap');
            const stockInp = document.getElementById('createStockQty');
            if (!createCategory) return;
            const isTobacco = createCategory.value === 'tobacco';
            if (priceWrap) priceWrap.style.display = isTobacco ? 'none' : '';
            if (hint) hint.style.display = isTobacco ? '' : 'none';
            if (pr) pr.required = !isTobacco;
            if (sale) sale.required = false;
            if (stockWrap) stockWrap.style.display = isTobacco ? 'none' : '';
            if (stockInp) stockInp.required = !isTobacco;
        }

        async function fetchSuggestions(term) {
            if (!term || term.length < 2) {
                imageSuggestions.innerHTML = '';
                imageSuggestions.style.display = 'none';
                return;
            }

            const response = await fetch(`{{ route('dashboard.products.image_suggestions') }}?q=${encodeURIComponent(term)}`);
            if (!response.ok) return;
            const items = await response.json();

            if (!Array.isArray(items) || items.length === 0) {
                imageSuggestions.innerHTML = '';
                imageSuggestions.style.display = 'none';
                return;
            }

            imageSuggestions.innerHTML = '';
            let autoApplied = false;
            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'suggestion-item';
                const label = item.source === 'products'
                    ? `${item.name} (от продукт)`
                    : item.name;
                button.innerHTML = `<img src="${item.url}" alt="${item.name}"><span>${label}</span>`;
                button.addEventListener('click', () => {
                    selectedImagePathInput.value = item.path;
                    fileName.textContent = `Избрана предложена снимка: ${item.name}`;
                    imageSuggestions.querySelectorAll('.suggestion-item').forEach((el) => el.classList.remove('active'));
                    button.classList.add('active');
                });
                imageSuggestions.appendChild(button);

                if (!autoApplied && (item.name || '').toLowerCase() === term.toLowerCase()) {
                    autoApplied = true;
                    selectedImagePathInput.value = item.path;
                    fileName.textContent = `Автоматично избрана снимка: ${item.name}`;
                    button.classList.add('active');
                }
            });
            imageSuggestions.style.display = 'grid';
        }

        if (q) q.addEventListener('input', submitDebounced);
        if (category) category.addEventListener('change', () => form.submit());
        if (sort) sort.addEventListener('change', () => form.submit());

        if (imageInput && fileName) {
            imageInput.addEventListener('change', () => {
                if (imageInput.files && imageInput.files[0]) {
                    fileName.textContent = imageInput.files[0].name;
                    selectedImagePathInput.value = '';
                    imageSuggestions.querySelectorAll('.suggestion-item').forEach((el) => el.classList.remove('active'));
                } else {
                    fileName.textContent = 'Няма избран файл';
                }
            });
        }

        if (productNameInput) {
            productNameInput.addEventListener('input', () => {
                clearTimeout(suggestTimeout);
                suggestTimeout = setTimeout(() => {
                    fetchSuggestions(productNameInput.value.trim()).catch(() => {});
                }, 250);
            });
        }

        document.querySelectorAll('[data-edit-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('[data-product-row]');
                if (row) row.classList.toggle('editing');
            });
        });

        document.querySelectorAll('[data-delete-btn]').forEach((btn) => {
            btn.addEventListener('click', () => {
                deleteForm = btn.closest('form');
                const name = btn.getAttribute('data-product-name') || 'този продукт';
                deleteModalText.textContent = `Сигурни ли сте, че искате да изтриете "${name}"?`;
                deleteModal.show();
            });
        });

        confirmDeleteBtn.addEventListener('click', () => {
            if (deleteForm) deleteForm.submit();
        });

        toggleProductFields('');
        if (createCategory) {
            createCategory.addEventListener('change', () => {
                toggleProductFields('');
                filterBrandOptions(createBrandSelect, createCategory.value);
            });
            filterBrandOptions(createBrandSelect, createCategory.value);
        }

        document.querySelectorAll('[data-product-row]').forEach((row) => {
            const select = row.querySelector('select[name="category"]');
            const brandSelect = row.querySelector('select[name="brand_id"]');
            const unitInput = row.querySelector('input[name="unit"]');
            const nameInput = row.querySelector('input[name="name"]');
            const priceBlock = row.querySelector('[data-edit-nontobacco-prices]');
            const stockBlock = row.querySelector('[data-edit-stock-fields]');
            if (!select || !unitInput) return;
            const apply = () => {
                const isTobacco = select.value === 'tobacco';
                const isHookah = select.value === 'hookah';
                unitInput.disabled = isTobacco;
                if (isTobacco) {
                    unitInput.value = 'g';
                }
                if (nameInput) {
                    nameInput.disabled = isHookah;
                    nameInput.required = !isHookah;
                    nameInput.style.display = isHookah ? 'none' : '';
                    if (isHookah) {
                        nameInput.value = '';
                    }
                }
                if (priceBlock) {
                    priceBlock.style.display = isTobacco ? 'none' : '';
                }
                if (stockBlock) {
                    stockBlock.style.display = isTobacco ? 'none' : '';
                    stockBlock.querySelectorAll('input').forEach((inp) => {
                        inp.required = !isTobacco;
                    });
                }
                filterBrandOptions(brandSelect, select.value);
            };
            apply();
            select.addEventListener('change', apply);
        });

        const sidebarBtn = document.querySelector('[data-sidebar-toggle]');
        const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
        sidebarBtn?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
        sidebarBackdrop?.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
    })();
</script>
</body>
</html>
