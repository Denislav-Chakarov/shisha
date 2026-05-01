<section class="grid product-create-grid">
    <article class="panel">
        <h2>Добавяне на категория</h2>
        <form method="POST" action="{{ route('dashboard.categories.store') }}">
            @csrf
            <input type="text" name="name" placeholder="Име" required maxlength="60">
            <input type="text" name="slug" placeholder="slug (по избор)" maxlength="40">
            <div class="muted small" style="margin-top:6px;">Родител (за подкатегория)</div>
            <select name="parent_id">
                <option value="">— без родител —</option>
                @foreach (($categories ?? collect()) as $pc)
                    <option value="{{ (int) $pc->id }}">{{ $pc->name }} ({{ $pc->slug }})</option>
                @endforeach
            </select>
            <div class="muted small" style="margin-top:6px;">Поведение (как се държи категорията)</div>
            <select name="behavior_type" required>
                <option value="tobacco">Тютюн</option>
                <option value="drink">Напитки</option>
                <option value="hookah">Наргилета</option>
                <option value="generic">Generic</option>
            </select>
            <div class="muted small" style="margin-top:6px;">Подредба</div>
            <input type="number" name="position" min="0" max="32767" value="0" placeholder="напр. 10">
            <button class="btn" type="submit">Запази категория</button>
        </form>
    </article>

    <article class="panel">
        <h2>Категории</h2>
        @php
            $page = $categoriesPage ?? ($categories ?? collect());
        @endphp

        @if (($page ?? collect())->isEmpty())
            <div class="muted">Няма категории.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-dark table-bordered align-middle mb-0">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Име</th>
                        <th>Slug</th>
                        <th>Родител</th>
                        <th>Поведение</th>
                        <th>Position</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($page as $cat)
                        @php
                            $parentName = null;
                            if (!empty($cat->parent_id) && isset($categories)) {
                                $p = $categories->firstWhere('id', $cat->parent_id);
                                $parentName = $p?->name;
                            }
                        @endphp
                        <tr>
                            <td>{{ (int) $cat->id }}</td>
                            <td>{{ $cat->name }}</td>
                            <td>{{ $cat->slug }}</td>
                            <td>{{ $parentName ?? '—' }}</td>
                            <td>{{ $cat->behavior_type }}</td>
                            <td>{{ (int) $cat->position }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-light" data-edit-toggle>Редактирай</button>
                                    <form method="POST" action="{{ route('dashboard.categories.delete', $cat->id) }}" onsubmit="return confirm('Изтриване на категорията?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Изтрий</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr class="d-none" data-edit-row>
                            <td colspan="7">
                                <form method="POST" action="{{ route('dashboard.categories.update', $cat->id) }}" class="d-flex gap-2 flex-wrap align-items-end">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" class="form-control form-control-sm" style="min-width:180px;" value="{{ $cat->name }}" required maxlength="60">
                                    <input type="text" name="slug" class="form-control form-control-sm" style="width:160px;" value="{{ $cat->slug }}" required maxlength="40">
                                    <div class="d-flex flex-column" style="min-width:220px;">
                                        <span class="muted small">Родител</span>
                                    <select name="parent_id" class="form-select form-select-sm" style="min-width:220px;">
                                        <option value="">— без родител —</option>
                                        @foreach (($categories ?? collect()) as $pc)
                                            @if ((int) $pc->id !== (int) $cat->id)
                                                <option value="{{ (int) $pc->id }}" @selected((int) ($cat->parent_id ?? 0) === (int) $pc->id)>{{ $pc->name }} ({{ $pc->slug }})</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    </div>
                                    <div class="d-flex flex-column" style="width:160px;">
                                        <span class="muted small">Поведение</span>
                                    <select name="behavior_type" class="form-select form-select-sm" style="width:160px;" required>
                                        <option value="tobacco" @selected($cat->behavior_type === 'tobacco')>tobacco</option>
                                        <option value="drink" @selected($cat->behavior_type === 'drink')>drink</option>
                                        <option value="hookah" @selected($cat->behavior_type === 'hookah')>hookah</option>
                                        <option value="generic" @selected($cat->behavior_type === 'generic')>generic</option>
                                    </select>
                                    </div>
                                    <div class="d-flex flex-column" style="width:130px;">
                                        <span class="muted small">Подредба</span>
                                        <input type="number" name="position" class="form-control form-control-sm" style="width:130px;" min="0" max="32767" value="{{ (int) $cat->position }}">
                                    </div>
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

