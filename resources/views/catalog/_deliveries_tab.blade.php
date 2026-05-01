@php
    $filters = $filters ?? ['q' => '', 'category' => 'tobacco', 'brand_id' => null, 'product_id' => null, 'sort' => 'name_asc', 'view' => 'list'];
@endphp

<form id="filtersFormDeliveries" class="filters" method="GET" action="{{ route('deliveries') }}">
    <input id="filterQ" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Търсене по име, бранд...">
    <select id="filterCategory" name="category">
        <option value="all" @selected(($filters['category'] ?? 'all') === 'all')>Всички категории</option>
        <option value="tobacco" @selected(($filters['category'] ?? 'all') === 'tobacco')>Тютюн</option>
        <option value="drink" @selected(($filters['category'] ?? 'all') === 'drink')>Напитки</option>
        <option value="hookah" @selected(($filters['category'] ?? 'all') === 'hookah')>Наргилета</option>
    </select>
    <select id="filterBrand" name="brand_id">
        <option value="" @selected(empty($filters['brand_id'] ?? null))>Всички брандове</option>
        @foreach (($filterBrands ?? collect()) as $b)
            <option value="{{ (int) $b->id }}" @selected((int) ($filters['brand_id'] ?? 0) === (int) $b->id)>{{ $b->name }}</option>
        @endforeach
    </select>
    <select id="filterProduct" name="product_id">
        <option value="" @selected(empty($filters['product_id'] ?? null))>Всички продукти</option>
        @foreach (($filterProducts ?? collect()) as $p)
            <option value="{{ (int) $p->id }}" @selected((int) ($filters['product_id'] ?? 0) === (int) $p->id)>{{ $p->name }}</option>
        @endforeach
    </select>
    <select id="filterSort" name="sort">
        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>По име</option>
        <option value="stock_asc" @selected(($filters['sort'] ?? '') === 'stock_asc')>Грамове (наличност) възх.</option>
        <option value="stock_desc" @selected(($filters['sort'] ?? '') === 'stock_desc')>Грамове (наличност) низх.</option>
    </select>
    <input type="hidden" name="view" value="list">
</form>

<section class="products-list">
    @forelse ($filteredProducts as $product)
        @php
            $tpbEdit = $tobaccoPackPurchasesByProduct ?? collect();
            $editPurchRows = $tpbEdit->get($product->id, collect());
            $gdel = $genericDeliveriesByProduct ?? collect();
            $genericRows = $gdel->get($product->id, collect());
            $behavior = $product->category_behavior ?? 'generic';
        @endphp
        <article class="list-row" data-product-row>
            <div class="info">
                <strong>{{ $product->name }}</strong>
                <span class="meta">{{ $product->brand_name }} • {{ $behavior === 'tobacco' ? 'Тютюн' : ($behavior === 'drink' ? 'Напитка' : ($behavior === 'hookah' ? 'Наргиле' : 'Категория')) }}</span>
            </div>
            <div class="kpi"><div class="label">Наличност</div><div class="value">{{ (int) $product->stock_quantity }} {{ $product->unit }}</div></div>
            <div class="actions">
                @if ($behavior === 'tobacco')
                    <button type="button" class="btn-secondary" data-quick-toggle>Бързо +</button>
                    <button type="button" class="btn-secondary" data-history-toggle>История</button>
                @else
                    <button type="button" class="btn-secondary" data-quick-toggle>Бързо +</button>
                    <button type="button" class="btn-secondary" data-history-toggle>История</button>
                @endif
            </div>
            <div class="edit-panel" style="grid-column:1 / -1;">
                @if ($behavior === 'tobacco')
                    <div class="d-none" data-quick-panel style="border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:10px;">
                        <div class="h6 mb-2">Бързо добавяне</div>
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
                            <button type="submit" class="btn btn-sm btn-primary">Добави</button>
                        </form>
                    </div>

                    <div class="d-none mt-2" data-history-panel style="border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:10px;">
                        <div class="h6 mb-2">История</div>
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
                    </div>
                @else
                    <div class="d-none" data-quick-panel style="border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:10px;">
                        <div class="h6 mb-2">Бързо добавяне</div>
                        <form method="POST" action="{{ route('dashboard.products.deliveries.store', $product->id) }}" class="d-flex flex-wrap gap-2 align-items-end">
                            @csrf
                            <div>
                                <label class="form-label small mb-0">Дата</label>
                                <input type="date" name="delivered_at" class="form-control form-control-sm" required value="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div>
                                <label class="form-label small mb-0">Количество</label>
                                <input type="number" name="quantity" class="form-control form-control-sm" min="1" step="1" required placeholder="10">
                            </div>
                            <div>
                                <label class="form-label small mb-0">Цена/бр (по избор)</label>
                                <input type="number" name="unit_cost" class="form-control form-control-sm" min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div style="min-width:220px;">
                                <label class="form-label small mb-0">Бележка</label>
                                <input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="...">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Добави</button>
                        </form>
                    </div>

                    <div class="d-none mt-2" data-history-panel style="border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:10px;">
                        <div class="h6 mb-2">История</div>
                        @if ($genericRows->isEmpty())
                            <p class="muted small mb-2">Няма записани зареждания.</p>
                        @else
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-dark table-bordered align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Количество</th>
                                        <th>Цена/бр</th>
                                        <th>Бележка</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($genericRows as $row)
                                        <tr>
                                            <td colspan="5" class="p-2 bg-black bg-opacity-25">
                                                <form method="POST" action="{{ route('dashboard.products.deliveries.update', [$product->id, $row->id]) }}" class="row g-2 align-items-end">
                                                    @csrf @method('PUT')
                                                    <div class="col-auto">
                                                        <label class="form-label small mb-0">Дата</label>
                                                        <input type="date" name="delivered_at" class="form-control form-control-sm" required value="{{ \Illuminate\Support\Carbon::parse($row->delivered_at)->format('Y-m-d') }}">
                                                    </div>
                                                    <div class="col-auto">
                                                        <label class="form-label small mb-0">Кол.</label>
                                                        <input type="number" name="quantity" class="form-control form-control-sm" min="1" step="1" required value="{{ (int) $row->quantity }}">
                                                    </div>
                                                    <div class="col-auto">
                                                        <label class="form-label small mb-0">Цена/бр</label>
                                                        <input type="number" name="unit_cost" class="form-control form-control-sm" min="0" step="0.01" value="{{ $row->unit_cost !== null ? (float) $row->unit_cost : '' }}">
                                                    </div>
                                                    <div class="col-auto" style="min-width:240px;">
                                                        <label class="form-label small mb-0">Бележка</label>
                                                        <input type="text" name="note" class="form-control form-control-sm" maxlength="255" value="{{ (string) ($row->note ?? '') }}">
                                                    </div>
                                                    <div class="col-auto">
                                                        <button type="submit" class="btn btn-sm btn-primary">Запази</button>
                                                    </div>
                                                </form>
                                                <form method="POST" action="{{ route('dashboard.products.deliveries.destroy', [$product->id, $row->id]) }}" class="d-inline mt-1" onsubmit="return confirm('Изтриване на зареждането?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0">Изтрий</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </article>
    @empty
        <article class="panel">Няма продукти.</article>
    @endforelse
</section>

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

<script>
    (function () {
        const form = document.getElementById('filtersFormDeliveries');
        const q = document.getElementById('filterQ');
        const category = document.getElementById('filterCategory');
        const brand = document.getElementById('filterBrand');
        const product = document.getElementById('filterProduct');
        const sort = document.getElementById('filterSort');

        let timeoutId = null;
        function submitDebounced() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => form.submit(), 250);
        }

        if (q) q.addEventListener('input', submitDebounced);
        if (category) category.addEventListener('change', () => {
            if (brand) brand.value = '';
            if (product) product.value = '';
            form.submit();
        });
        if (brand) brand.addEventListener('change', () => {
            if (product) product.value = '';
            form.submit();
        });
        if (product) product.addEventListener('change', () => form.submit());
        if (sort) sort.addEventListener('change', () => form.submit());

        document.querySelectorAll('[data-quick-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('[data-product-row]');
                const panel = row?.querySelector('[data-quick-panel]');
                if (!panel) return;
                row.classList.add('editing');
                panel.classList.toggle('d-none');
            });
        });

        document.querySelectorAll('[data-history-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('[data-product-row]');
                const panel = row?.querySelector('[data-history-panel]');
                if (!panel) return;
                row.classList.add('editing');
                panel.classList.toggle('d-none');
            });
        });
    })();
</script>

