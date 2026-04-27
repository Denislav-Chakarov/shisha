<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Shisha</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="page-login">
    <main class="login-card">
        <p class="brand">Tobacco Lounge</p>
        <h1>Добре дошли отново</h1>
        <p class="subtitle">Влезте, за да управлявате наличности, маси и поръчки.</p>

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <div class="field">
                <label for="username">Потребителско име</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus>
                @error('username')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="password">Парола</label>
                <input id="password" name="password" type="password" required>
                @error('password')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <label class="remember" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                Запомни ме
            </label>

            <button class="btn" type="submit">Вход</button>
        </form>

        <p class="hint">Профилите се управляват директно в базата данни.</p>
    </main>
</body>
</html>
