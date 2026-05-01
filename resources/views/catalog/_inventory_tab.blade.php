@php
    $filters = $filters ?? ['q' => '', 'category' => 'all', 'sort' => 'name_asc', 'view' => 'list'];
@endphp

<form id="filtersFormInventory" class="filters" method="GET" action="{{ route('products') }}">
    <input type="hidden" name="tab" value="inventory">
    <input id="filterQ" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Търсене по име, бранд, вкус...">
    <select id="filterCategoryBehavior" name="category">
        <option value="all" @selected(($filters['category'] ?? 'all') === 'all')>Всички типове</option>
        <option value="tobacco" @selected(($filters['category'] ?? 'all') === 'tobacco')>Тютюн</option>
        <option value="drink" @selected(($filters['category'] ?? 'all') === 'drink')>Напитки</option>
        <option value="hookah" @selected(($filters['category'] ?? 'all') === 'hookah')>Наргилета</option>
    </select>
    <select id="filterParentCategory">
        <option value="">Категория (по избор)</option>
        @foreach (($rootCategories ?? $categories ?? collect()) as $cat)
            <option value="{{ (int) $cat->id }}">{{ $cat->name }}</option>
        @endforeach
    </select>
    <select id="filterSubcategory" name="category_id" style="display:none;">
        <option value="">Подкатегория (по избор)</option>
        @foreach (($childCategories ?? collect()) as $cat)
            <option value="{{ (int) $cat->id }}" data-parent-id="{{ (int) $cat->parent_id }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $cat->id)>{{ $cat->name }}</option>
        @endforeach
    </select>
    <select id="filterSort" name="sort">
        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>По име</option>
        <option value="stock_asc" @selected(($filters['sort'] ?? '') === 'stock_asc')>Наличност възходящо</option>
        <option value="stock_desc" @selected(($filters['sort'] ?? '') === 'stock_desc')>Наличност низходящо</option>
    </select>
    <input type="hidden" name="view" value="{{ $filters['view'] ?? 'list' }}">
    <div class="view-toggle">
        <a class="{{ ($filters['view'] ?? 'list') === 'list' ? 'active' : '' }}"
           href="{{ route('products', ['tab' => 'inventory','q' => $filters['q'] ?? '', 'category' => $filters['category'] ?? 'all', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => 'list']) }}">Вертикално</a>
        <a class="{{ ($filters['view'] ?? 'list') === 'grid' ? 'active' : '' }}"
           href="{{ route('products', ['tab' => 'inventory','q' => $filters['q'] ?? '', 'category' => $filters['category'] ?? 'all', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => 'grid']) }}">Карти</a>
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
                $behavior = $product->category_behavior ?? 'generic';
                $behaviorLabel = $behavior === 'drink' ? 'Напитка' : ($behavior === 'hookah' ? 'Наргиле' : ($behavior === 'tobacco' ? 'Тютюн' : 'Категория'));
                $tpb = $tobaccoPackPurchasesByProduct ?? collect();
                $gridPurch = $tpb->get($product->id, collect());
                $gridLatest = $gridPurch->first();
            @endphp
            <article class="card">
                <div class="img-wrap">
                    @if ($imageUrl) <img src="{{ $imageUrl }}" alt="{{ $product->name }}"> @else <div class="img-placeholder">Няма снимка</div> @endif
                </div>
                <div class="card-body">
                    <div class="title">{{ $product->name }}</div>
                    <div class="meta">{{ $product->brand_name }} • {{ $behaviorLabel }}</div>
                    @if ($behavior === 'tobacco' && $gridLatest)
                        <div class="row muted" style="font-size:0.85em;">
                            Последно зареждане: {{ \Illuminate\Support\Carbon::parse($gridLatest->restocked_at)->format('d.m.Y') }}
                            · {{ (int) $gridLatest->pack_grams }}g × {{ (int) $gridLatest->boxes_count }} кут.
                        </div>
                    @endif
                    <div class="row">
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
                $behavior = $product->category_behavior ?? 'generic';
                $behaviorLabel = $behavior === 'drink' ? 'Напитка' : ($behavior === 'hookah' ? 'Наргиле' : ($behavior === 'tobacco' ? 'Тютюн' : 'Категория'));
                $tpbList = $tobaccoPackPurchasesByProduct ?? collect();
                $listPurch = $tpbList->get($product->id, collect());
                $listLatest = $listPurch->first();
            @endphp
            <article class="list-row" data-product-row>
                <div class="list-thumb">
                    @if ($imageUrl) <img src="{{ $imageUrl }}" alt="{{ $product->name }}"> @else <div class="img-placeholder">Без снимка</div> @endif
                </div>
                <div class="info">
                    <strong>{{ $product->name }}</strong>
                    <span class="meta">{{ $product->brand_name }} • {{ $behaviorLabel }}</span>
                    @if ($behavior === 'tobacco' && $listLatest)
                        <span class="meta" style="display:block;margin-top:4px;">
                            Последно: {{ \Illuminate\Support\Carbon::parse($listLatest->restocked_at)->format('d.m.Y') }}
                            · {{ (int) $listLatest->pack_grams }}g × {{ (int) $listLatest->boxes_count }} кут.
                        </span>
                    @endif
                </div>
                <div class="kpi"><div class="label">Наличност</div><div class="value">{{ $product->stock_quantity }} {{ $product->unit }}</div></div>
                <div class="actions">
                    <button type="button" class="btn-secondary" data-edit-toggle>Редактирай</button>
                </div>
                <div class="edit-panel" style="grid-column:1 / -1;">
                    @php
                        $tpbEdit = $tobaccoPackPurchasesByProduct ?? collect();
                        $editPurchRows = $tpbEdit->get($product->id, collect());
                        $tinvEdit = $tobaccoPackInventoryByProduct ?? collect();
                        $editInvRows = $tinvEdit->get($product->id, collect());
                    @endphp

                    <form method="POST" action="{{ route('dashboard.products.update', $product->id) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="brand_id" value="{{ (int) $product->brand_id }}">
                        <input type="hidden" name="category_id" value="{{ (int) $product->category_id }}">
                        <input type="hidden" name="name" value="{{ $product->name }}">
                        <input type="hidden" name="unit" value="{{ $product->unit }}">
                        <input type="hidden" name="purchase_price" value="{{ $product->purchase_price ?? 0 }}">
                        <input type="hidden" name="sale_price" value="{{ $product->price ?? 0 }}">
                        <input type="hidden" name="is_active" value="{{ $product->is_active ? 1 : 0 }}">
                        @if ($behavior !== 'hookah')
                            <div class="edit-grid" style="margin-bottom:8px;">
                                <div>
                                    <select name="writeoff_mode" data-writeoff-mode>
                                        <option value="manual" @selected(($product->writeoff_mode ?? 'manual') === 'manual')>Изписване: ръчно</option>
                                        <option value="recipe" @selected(($product->writeoff_mode ?? 'manual') === 'recipe')>Изписване: чрез рецепта</option>
                                        <option value="auto" @selected(($product->writeoff_mode ?? 'manual') === 'auto')>Изписване: автоматично</option>
                                    </select>
                                </div>
                                <div>
                                </div>
                            </div>
                        @else
                            <input type="hidden" name="writeoff_mode" value="{{ $product->writeoff_mode ?? 'recipe' }}">
                        @endif

                        <div class="edit-grid">
                            @if ($behavior !== 'tobacco')
                                <div>
                                    <input type="number" min="0" name="stock_quantity" value="{{ $product->stock_quantity }}" placeholder="Наличност">
                                </div>
                                <div>
                                    <input type="number" min="0" name="restock_add" value="0" placeholder="Добави към наличност (+)">
                                </div>
                                <button class="btn" type="submit">Запази наличност</button>
                            @else
                                <div class="muted">За тютюн наличността се смята от кутиите по разфасовка (виж по-долу).</div>
                            @endif
                        </div>
                    </form>

                    @if ($behavior === 'tobacco')
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
                            <p class="muted small mt-2 mb-2">Пазарна цена за кутия; при запис кутиите към съответната разфасовка се увеличават.</p>
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

<script>
    (function () {
        const form = document.getElementById('filtersFormInventory');
        const q = document.getElementById('filterQ');
        const behavior = document.getElementById('filterCategoryBehavior');
        const parent = document.getElementById('filterParentCategory');
        const sub = document.getElementById('filterSubcategory');
        const sort = document.getElementById('filterSort');

        let timeoutId = null;
        function submitDebounced() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => form.submit(), 250);
        }

        if (q) q.addEventListener('input', submitDebounced);
        if (behavior) behavior.addEventListener('change', () => {
            if (sub) sub.value = '';
            form.submit();
        });
        if (sort) sort.addEventListener('change', () => form.submit());

        function syncSubcats() {
            if (!parent || !sub) return;
            const pid = parent.value;
            const options = Array.from(sub.querySelectorAll('option'));
            let hasAny = false;
            options.forEach((opt, idx) => {
                if (idx === 0) return;
                const opid = opt.getAttribute('data-parent-id');
                const visible = opid === String(pid);
                opt.hidden = !visible;
                if (visible) hasAny = true;
            });
            sub.style.display = hasAny ? '' : 'none';
            if (!hasAny) sub.value = '';
        }

        parent?.addEventListener('change', () => {
            syncSubcats();
            if (sub) sub.value = '';
            form.submit();
        });
        sub?.addEventListener('change', () => form.submit());
        syncSubcats();

        document.querySelectorAll('[data-edit-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('[data-product-row]');
                if (row) row.classList.toggle('editing');
            });
        });

        function syncRowTiming() {}

        document.querySelectorAll('[data-product-row]').forEach((row) => {
            const mode = row.querySelector('[data-writeoff-mode]');
            mode?.addEventListener('change', () => syncRowTiming());
            syncRowTiming();
        });
    })();
</script>

