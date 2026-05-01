<section class="grid product-create-grid">
    <article class="panel">
        <h2>Добавяне на бранд</h2>
        <form method="POST" action="{{ route('dashboard.brands.store') }}">
            @csrf
            <input type="text" name="name" placeholder="Име на бранд" required maxlength="255">
            <select name="category_id" required>
                <option value="">Изберете категория</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->behavior_type }})</option>
                @endforeach
            </select>
            <button class="btn" type="submit">Запази бранд</button>
        </form>
    </article>

    <article class="panel">
        <h2>Брандове</h2>
        @php
            $page = $brandsPage ?? ($brands ?? collect());
        @endphp

        @if (($page ?? collect())->isEmpty())
            <div class="muted">Няма брандове.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-dark table-bordered align-middle mb-0">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Име</th>
                        <th>Категория</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($page as $brand)
                        <tr>
                            <td>{{ (int) $brand->id }}</td>
                            <td>{{ $brand->name }}</td>
                            <td>
                                {{ $brand->category?->name ?? '—' }}
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-light" data-edit-toggle>Редактирай</button>
                                    <form method="POST" action="{{ route('dashboard.brands.delete', $brand->id) }}" onsubmit="return confirm('Изтриване на бранда?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Изтрий</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr class="d-none" data-edit-row>
                            <td colspan="4">
                                <form method="POST" action="{{ route('dashboard.brands.update', $brand->id) }}" class="d-flex gap-2 flex-wrap align-items-end">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" class="form-control form-control-sm" style="min-width:220px;" value="{{ $brand->name }}" required maxlength="255">
                                    <select name="category_id" class="form-select form-select-sm" style="min-width:220px;" required>
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat->id }}" @selected((int) $brand->category_id === (int) $cat->id)>{{ $cat->name }} ({{ $cat->behavior_type }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Запази</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-cancel>Отказ</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if ($page instanceof \Illuminate\Pagination\LengthAwarePaginator && $page->lastPage() > 1)
                <nav class="pagination mt-2">
                    @if ($page->onFirstPage())
                        <span>Предишна</span>
                    @else
                        <a href="{{ $page->previousPageUrl() }}">Предишна</a>
                    @endif
                    @for ($p = 1; $p <= $page->lastPage(); $p++)
                        @if ($p == $page->currentPage())
                            <span class="active">{{ $p }}</span>
                        @else
                            <a href="{{ $page->url($p) }}">{{ $p }}</a>
                        @endif
                    @endfor
                    @if ($page->hasMorePages())
                        <a href="{{ $page->nextPageUrl() }}">Следваща</a>
                    @else
                        <span>Следваща</span>
                    @endif
                </nav>
            @endif
        @endif
    </article>
</section>

<script>
    (function () {
        document.querySelectorAll('[data-edit-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                const editRow = row?.nextElementSibling;
                if (editRow && editRow.hasAttribute('data-edit-row')) {
                    editRow.classList.toggle('d-none');
                }
            });
        });

        document.querySelectorAll('[data-edit-cancel]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const editRow = btn.closest('tr[data-edit-row]');
                if (editRow) editRow.classList.add('d-none');
            });
        });
    })();
</script>

