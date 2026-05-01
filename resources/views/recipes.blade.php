<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рецепти</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="page-products">
<div class="layout">
    <aside class="sidebar">
        <div class="logo">TOBACCO</div>
        <a class="nav-link" href="{{ route('dashboard') }}"><span class="ic">□</span>Табло</a>
        <div class="menu-title">Управление</div>
        <a class="nav-link" href="{{ route('products') }}"><span class="ic">◈</span>Продукти</a>
        <a class="nav-link" href="{{ route('take_order') }}"><span class="ic">◍</span>Вземи поръчка</a>
        <a class="nav-link active" href="{{ route('recipes') }}"><span class="ic">◌</span>Рецепти</a>
        <a class="nav-link" href="{{ route('products') }}"><span class="ic">◧</span>Наличности</a>
        <a class="nav-link" href="{{ route('invoice_import') }}"><span class="ic">◇</span>Импорт фактура</a>
        <div class="menu-title">Анализи</div>
        <a class="nav-link" href="{{ route('reports') }}"><span class="ic">◫</span>Справки</a>
    </aside>

    <main class="main">
        <div class="header">Рецепти</div>
        @if (session('status')) <div class="msg ok">{{ session('status') }}</div> @endif
        @if (session('error')) <div class="msg err">{{ session('error') }}</div> @endif

        <section class="content">
            <article class="panel">
                <h2>Напитки по бранд</h2>
                @forelse (($drinkProducts ?? collect()) as $brand => $items)
                    <div style="margin-bottom:10px;">
                        <strong>{{ $brand }}</strong>
                        <div class="muted">{{ $items->pluck('name')->join(', ') }}</div>
                    </div>
                @empty
                    <div class="muted">Няма въведени напитки.</div>
                @endforelse
            </article>

            <article class="panel">
                <h2>Рецепти за наргиле</h2>
                @forelse (($hookahRecipes ?? collect()) as $hookahName => $rows)
                    <div style="margin-bottom:10px;">
                        <strong>{{ $hookahName }}</strong>
                        @foreach ($rows as $row)
                            <div class="muted">{{ $row->tobacco_name }} - {{ number_format((float) $row->grams_per_serving, 1) }}g</div>
                        @endforeach
                    </div>
                @empty
                    <div class="muted">Няма въведени рецепти за наргиле.</div>
                @endforelse
            </article>
        </section>
    </main>
</div>
</body>
</html>

