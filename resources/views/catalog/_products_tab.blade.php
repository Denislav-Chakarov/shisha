@php
    $filters = $filters ?? ['q' => '', 'category' => 'all', 'sort' => 'name_asc', 'view' => 'list'];
@endphp

<section class="grid product-create-grid">
    <article class="panel">
        <h2>Добавяне на продукт</h2>
        <form method="POST" action="{{ route('dashboard.products.store') }}" enctype="multipart/form-data">
            @csrf
            <select id="createParentCategorySelect" required>
                <option value="">Изберете категория</option>
                @foreach (($rootCategories ?? $categories) as $cat)
                    <option value="{{ $cat->id }}" data-behavior="{{ $cat->behavior_type }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <select id="createSubcategorySelect" style="display:none;">
                <option value="">Изберете подкатегория</option>
                @foreach (($childCategories ?? collect()) as $cat)
                    <option value="{{ $cat->id }}" data-parent-id="{{ (int) $cat->parent_id }}" data-behavior="{{ $cat->behavior_type }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <input type="hidden" name="category_id" id="createCategoryId">
            <select id="createBrandSelect" name="brand_id" required>
                <option value="">Изберете бранд</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}"
                            data-category-id="{{ (int) $brand->category_id }}"
                            data-behavior="{{ $brand->category?->behavior_type ?? 'generic' }}">{{ $brand->name }}</option>
                @endforeach
            </select>
            <input id="createProductName" type="text" name="name" placeholder="Име на продукт">
            <input type="hidden" name="selected_image_path" id="selectedImagePath">
            <div id="imageSuggestions" class="image-suggestions" style="display:none;"></div>
            <div id="createNontobaccoPriceFields">
                <input type="number" step="0.01" min="0" name="purchase_price" id="createPurchasePrice" placeholder="Пазарна цена (€)">
                <input type="number" step="0.01" min="0" name="sale_price" id="createSalePrice" placeholder="Продажна цена (€) — по избор">
            </div>
            <input type="text" name="unit" value="бр" placeholder="Мерна единица (за напитки)">
            <div data-writeoff-wrap>
                <select name="writeoff_mode">
                    <option value="manual" selected>Изписване: ръчно</option>
                    <option value="recipe">Изписване: чрез рецепта</option>
                    <option value="auto">Изписване: автоматично</option>
                </select>
            </div>
            <label for="image" class="file-picker">Качи снимка (JPG, PNG, WEBP)</label>
            <input id="image" class="file-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
            <div id="fileName" class="file-name">Няма избран файл</div>
            <label class="muted small"><input type="checkbox" name="is_active" value="1" checked> Активен</label>
            <button class="btn" type="submit">Запази продукт</button>
        </form>
    </article>
</section>

<form id="filtersForm" class="filters" method="GET" action="{{ route('products') }}">
    <input type="hidden" name="tab" value="products">
    <input id="filterQ" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Търсене по име, бранд, вкус...">
    <select id="filterCategory" name="category">
        <option value="all" @selected(($filters['category'] ?? 'all') === 'all')>Всички типове</option>
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
    <select id="filterSort" name="sort">
        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>По име</option>
        <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Продажна цена възходящо</option>
        <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Продажна цена низходящо</option>
    </select>
    <input type="hidden" name="view" value="{{ $filters['view'] ?? 'list' }}">
    <div class="view-toggle">
        <a class="{{ ($filters['view'] ?? 'list') === 'list' ? 'active' : '' }}"
           href="{{ route('products', ['tab' => 'products','q' => $filters['q'] ?? '', 'category' => $filters['category'] ?? 'all', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => 'list']) }}">Вертикално</a>
        <a class="{{ ($filters['view'] ?? 'list') === 'grid' ? 'active' : '' }}"
           href="{{ route('products', ['tab' => 'products','q' => $filters['q'] ?? '', 'category' => $filters['category'] ?? 'all', 'sort' => $filters['sort'] ?? 'name_asc', 'view' => 'grid']) }}">Карти</a>
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
            @endphp
            <article class="card">
                <div class="img-wrap">
                    @if ($imageUrl) <img src="{{ $imageUrl }}" alt="{{ $product->name }}"> @else <div class="img-placeholder">Няма снимка</div> @endif
                </div>
                <div class="card-body">
                    <div class="title">{{ $product->name }}</div>
                    <div class="meta">{{ $product->brand_name }} • {{ $behaviorLabel }}</div>
                    <div class="row">
                        <span>
                            @if ($behavior === 'tobacco')
                                цени в зарежданията
                            @else
                                Прод. €{{ number_format($product->price, 2) }} · Паз. €{{ number_format($product->purchase_price ?? 0, 2) }}
                            @endif
                        </span>
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
            @endphp
            <article class="list-row" data-product-row>
                <div class="list-thumb">
                    @if ($imageUrl) <img src="{{ $imageUrl }}" alt="{{ $product->name }}"> @else <div class="img-placeholder">Без снимка</div> @endif
                </div>
                <div class="info">
                    <strong>{{ $product->name }}</strong>
                    <span class="meta">{{ $product->brand_name }} • {{ $behaviorLabel }}</span>
                </div>
                @if ($behavior === 'tobacco')
                    <div class="kpi"><div class="label">Цени</div><div class="value">—</div></div>
                @else
                    <div class="kpi"><div class="label">Продажна</div><div class="value">€{{ number_format($product->price, 2) }}</div></div>
                    <div class="kpi"><div class="label">Пазарна</div><div class="value">€{{ number_format($product->purchase_price ?? 0, 2) }}</div></div>
                @endif
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
                            <select name="category_id" required data-edit-category>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                            data-behavior="{{ $cat->behavior_type }}"
                                            @selected((int) $product->category_id === (int) $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <select name="brand_id" required data-edit-brand>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}"
                                            data-category-id="{{ (int) $brand->category_id }}"
                                            @selected((int) $product->brand_id === (int) $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="name" value="{{ $product->name }}">
                            <div class="edit-nontobacco-prices" data-edit-nontobacco-prices style="{{ $behavior === 'tobacco' ? 'display:none;' : '' }}">
                                @if ($behavior === 'drink')
                                    <input type="hidden" name="purchase_price" value="{{ $product->purchase_price ?? 0 }}">
                                @else
                                    <input type="number" step="0.01" min="0" name="purchase_price" value="{{ $product->purchase_price ?? 0 }}" placeholder="Пазарна цена (€)">
                                @endif
                                <input type="number" step="0.01" min="0" name="sale_price" value="{{ $product->price }}" placeholder="Продажна цена (€)">
                            </div>
                            <input type="text" name="unit" value="{{ $product->unit }}">
                            @if ($behavior !== 'hookah')
                                <div data-writeoff-wrap>
                                    <select name="writeoff_mode">
                                        <option value="manual" @selected(($product->writeoff_mode ?? 'manual') === 'manual')>Изписване: ръчно</option>
                                        <option value="recipe" @selected(($product->writeoff_mode ?? 'manual') === 'recipe')>Изписване: чрез рецепта</option>
                                        <option value="auto" @selected(($product->writeoff_mode ?? 'manual') === 'auto')>Изписване: автоматично</option>
                                    </select>
                                </div>
                            @else
                                <input type="hidden" name="writeoff_mode" value="{{ $product->writeoff_mode ?? 'recipe' }}">
                            @endif
                            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                            <label><input type="checkbox" name="is_active" value="1" @checked($product->is_active)> Активен</label>
                            <button class="btn" type="submit">Запази промени</button>
                        </div>
                    </form>
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

<script>
    (function () {
        const form = document.getElementById('filtersForm');
        const q = document.getElementById('filterQ');
        const category = document.getElementById('filterCategory');
        const brand = document.getElementById('filterBrand');
        const sort = document.getElementById('filterSort');
        const imageInput = document.getElementById('image');
        const fileName = document.getElementById('fileName');
        const createParentCategory = document.getElementById('createParentCategorySelect');
        const createSubcategory = document.getElementById('createSubcategorySelect');
        const createCategoryId = document.getElementById('createCategoryId');
        const createBrandSelect = document.getElementById('createBrandSelect');
        const productNameInput = document.getElementById('createProductName');
        const selectedImagePathInput = document.getElementById('selectedImagePath');
        const imageSuggestions = document.getElementById('imageSuggestions');
        const deleteModalEl = document.getElementById('deleteProductModal');
        const deleteModalText = document.getElementById('deleteModalText');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const deleteModal = (window.bootstrap && bootstrap.Modal && deleteModalEl)
            ? new bootstrap.Modal(deleteModalEl)
            : null;
        let deleteForm = null;
        let timeoutId = null;
        let suggestTimeout = null;

        function submitDebounced() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => form.submit(), 250);
        }

        function getBehaviorFromCategorySelect(selectEl) {
            const opt = selectEl?.options?.[selectEl.selectedIndex];
            return opt?.getAttribute?.('data-behavior') || 'generic';
        }

        function filterBrandOptions(brandSelect, categoryId) {
            if (!brandSelect) return;
            const options = Array.from(brandSelect.querySelectorAll('option'));
            options.forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }
                const optionCategoryId = option.getAttribute('data-category-id') || '';
                option.hidden = categoryId ? (String(optionCategoryId) !== String(categoryId)) : false;
            });
            const selectedOption = brandSelect.options[brandSelect.selectedIndex];
            if (selectedOption && selectedOption.hidden) {
                brandSelect.value = '';
            }
        }

        function syncCreateUi() {
            if (!createParentCategory) return;
            const behavior = getBehaviorFromCategorySelect(createParentCategory);
            const isHookah = behavior === 'hookah';

            const priceWrap = document.getElementById('createNontobaccoPriceFields');
            const pr = document.getElementById('createPurchasePrice');
            const sale = document.getElementById('createSalePrice');

            if (priceWrap) priceWrap.style.display = behavior === 'tobacco' ? 'none' : '';
            if (pr) pr.required = behavior !== 'tobacco';
            if (sale) sale.required = false;

            if (productNameInput) {
                productNameInput.disabled = isHookah;
                productNameInput.required = !isHookah;
                productNameInput.style.display = isHookah ? 'none' : '';
                if (isHookah) productNameInput.value = '';
            }

            // For hookah products we keep legacy logic; hide writeoff config.
            const writeoffWrap = createParentCategory.closest('form')?.querySelector('[data-writeoff-wrap]');
            if (writeoffWrap) {
                writeoffWrap.style.display = isHookah ? 'none' : '';
            }
        }

        function syncWriteoffTimingInContainer() {}

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
        if (category) category.addEventListener('change', () => {
            if (brand) brand.value = '';
            form.submit();
        });
        if (brand) brand.addEventListener('change', () => form.submit());
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
                if (deleteModal) {
                    deleteModal.show();
                } else {
                    if (confirm(deleteModalText.textContent)) {
                        deleteForm?.submit();
                    }
                }
            });
        });

        confirmDeleteBtn?.addEventListener('click', () => {
            if (deleteForm) deleteForm.submit();
        });

        function syncCreateSubcategories() {
            if (!createParentCategory || !createSubcategory || !createCategoryId) return;
            const parentId = createParentCategory.value;
            const options = Array.from(createSubcategory.querySelectorAll('option'));
            let hasAny = false;
            options.forEach((opt, idx) => {
                if (idx === 0) return;
                const pid = opt.getAttribute('data-parent-id');
                const visible = pid === String(parentId);
                opt.hidden = !visible;
                if (visible) hasAny = true;
            });
            createSubcategory.style.display = hasAny ? '' : 'none';
            if (hasAny) {
                // keep selection if still valid; otherwise reset
                const sel = createSubcategory.options[createSubcategory.selectedIndex];
                if (!sel || sel.hidden) createSubcategory.value = '';
                createCategoryId.value = createSubcategory.value || '';
            } else {
                createSubcategory.value = '';
                createCategoryId.value = parentId;
            }
        }

        if (createParentCategory) {
            createParentCategory.addEventListener('change', () => {
                syncCreateSubcategories();
                // brand filtering is tied to final category id
                filterBrandOptions(createBrandSelect, createCategoryId?.value || '');
                syncCreateUi();
                syncWriteoffTimingInContainer();
            });
            createSubcategory?.addEventListener('change', () => {
                syncCreateSubcategories();
                filterBrandOptions(createBrandSelect, createCategoryId?.value || '');
            });
            syncCreateSubcategories();
            filterBrandOptions(createBrandSelect, createCategoryId?.value || '');
            syncCreateUi();
            syncWriteoffTimingInContainer();
        }

        document.querySelectorAll('[data-product-row]').forEach((row) => {
            const categorySelect = row.querySelector('[data-edit-category]');
            const brandSelect = row.querySelector('[data-edit-brand]');
            const unitInput = row.querySelector('input[name="unit"]');
            const nameInput = row.querySelector('input[name="name"]');
            const priceBlock = row.querySelector('[data-edit-nontobacco-prices]');
            if (!categorySelect) return;

            const apply = () => {
                const behavior = getBehaviorFromCategorySelect(categorySelect);
                const isHookah = behavior === 'hookah';
                if (unitInput) {
                    unitInput.disabled = behavior === 'tobacco';
                    if (behavior === 'tobacco') unitInput.value = 'g';
                }
                if (nameInput) {
                    nameInput.disabled = isHookah;
                    nameInput.required = !isHookah;
                    nameInput.style.display = isHookah ? 'none' : '';
                    if (isHookah) nameInput.value = '';
                }
                if (priceBlock) priceBlock.style.display = behavior === 'tobacco' ? 'none' : '';
                filterBrandOptions(brandSelect, categorySelect.value);
                syncWriteoffTimingInContainer();
            };

            apply();
            categorySelect.addEventListener('change', apply);
            const modeSelect = row.querySelector('select[name="writeoff_mode"]');
            modeSelect?.addEventListener('change', () => syncWriteoffTimingInContainer());
        });

        // create form
        const createForm = document.querySelector('form[action*="/dashboard/products"]');
        const createMode = createForm?.querySelector?.('select[name="writeoff_mode"]');
        createMode?.addEventListener('change', () => syncWriteoffTimingInContainer());
        if (createForm) syncWriteoffTimingInContainer();
    })();
</script>

