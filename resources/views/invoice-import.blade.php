<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Импорт фактура</title>
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
        <a class="nav-link" href="{{ route('recipes') }}"><span class="ic">◌</span>Рецепти</a>
        <a class="nav-link" href="{{ route('products') }}"><span class="ic">◧</span>Наличности</a>
        <a class="nav-link active" href="{{ route('invoice_import') }}"><span class="ic">◇</span>Импорт фактура</a>
        <div class="menu-title">Анализи</div>
        <a class="nav-link" href="{{ route('reports') }}"><span class="ic">◫</span>Справки</a>
    </aside>

    <main class="main">
        <div class="header">Импорт на наличност по фактура</div>
        <section class="panel" style="margin-top:10px;">
            <h2>Подготовка (без функционалност)</h2>
            <p class="muted">Този таб е подготвен за бъдещ импорт от фактура. В момента служи като placeholder по задание.</p>
            <form>
                <input type="text" placeholder="Номер на фактура" disabled>
                <input type="text" placeholder="Доставчик" disabled>
                <input type="file" disabled>
                <button class="btn" type="button" disabled>Импортирай</button>
            </form>
        </section>
    </main>
</div>
</body>
</html>

