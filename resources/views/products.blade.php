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
                    <input type="number" step="0.01" min="0" name="price" placeholder="Цена (за напитки)">
                    <input type="number" min="0" name="stock_quantity" placeholder="Наличност" required>
                    <input type="text" name="unit" value="бр" placeholder="Мерна единица (за напитки)">
                    <label for="image" class="file-picker">Качи снимка (JPG, PNG, WEBP)</label>
                    <input id="image" class="file-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                    <div id="fileName" class="file-name">Няма избран файл</div>
                    <button class="btn" type="submit">Запази продукт</button>
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
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Цена възходящо</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Цена низходящо</option>
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
                            <div class="row"><span>€{{ number_format($product->price, 2) }}</span><span>{{ $product->stock_quantity }} {{ $product->unit }}</span></div>
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
                        </div>
                        <div class="kpi"><div class="label">Цена</div><div class="value">€{{ number_format($product->price, 2) }}</div></div>
                        <div class="kpi"><div class="label">Наличност</div><div class="value">{{ $product->stock_quantity }} {{ $product->unit }}</div></div>
                        <div class="actions">
                            <button type="button" class="btn-secondary" data-edit-toggle>Редактирай</button>
                            <form method="POST" action="{{ route('dashboard.products.delete', $product->id) }}">
                                @csrf @method('DELETE')
                                <button class="btn-danger" type="button" data-delete-btn data-product-name="{{ $product->name }}">Изтрий</button>
                            </form>
                        </div>
                        <div class="edit-panel" style="grid-column:1 / -1;">
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
                                    <input type="number" step="0.01" min="0" name="price" value="{{ $product->price }}">
                                    <input type="number" min="0" name="stock_quantity" value="{{ $product->stock_quantity }}" required>
                                    <input type="text" name="unit" value="{{ $product->unit }}">
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
            const priceInput = document.querySelector(`${prefix}input[name="price"]`);
            const unitInput = document.querySelector(`${prefix}input[name="unit"]`);
            const nameInput = document.querySelector(`${prefix}input[name="name"]`);
            if (!categorySelect || !priceInput || !unitInput) return;
            const isTobacco = categorySelect.value === 'tobacco';
            const isHookah = categorySelect.value === 'hookah';
            priceInput.disabled = isTobacco;
            unitInput.disabled = isTobacco;
            if (isTobacco) {
                priceInput.value = '';
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
            const priceInput = row.querySelector('input[name="price"]');
            const unitInput = row.querySelector('input[name="unit"]');
            const nameInput = row.querySelector('input[name="name"]');
            if (!select || !priceInput || !unitInput) return;
            const apply = () => {
                const isTobacco = select.value === 'tobacco';
                const isHookah = select.value === 'hookah';
                priceInput.disabled = isTobacco;
                unitInput.disabled = isTobacco;
                if (isTobacco) {
                    priceInput.value = '0';
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
