<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Password — Commitment Corner</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f5f5f7;
            color: #1f2937;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
            padding: 28px 24px;
        }
        .brand {
            text-align: center;
            font-weight: 800;
            color: #ab2f2b;
            font-size: 20px;
            margin-bottom: 4px;
        }
        h1 { font-size: 18px; text-align: center; margin: 8px 0 2px; }
        .sub { text-align: center; color: #6b7280; font-size: 13px; margin-bottom: 20px; }
        .who {
            background: #faf5f5;
            border: 1px solid #f0dede;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .who b { color: #1f2937; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input[type=password] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 14px;
        }
        input:focus { outline: none; border-color: #ab2f2b; box-shadow: 0 0 0 3px rgba(171, 47, 43, .12); }
        button {
            width: 100%;
            padding: 11px;
            background: #ab2f2b;
            color: #fff;
            border: 0;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #8f2420; }
        .errors {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .errors ul { margin: 0; padding-left: 18px; }
        .hint { font-size: 12px; color: #9ca3af; margin-top: 14px; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">Commitment Corner</div>
        <h1>Set your password</h1>
        <p class="sub">Create a password to access your account.</p>

        <div class="who">
            <b>{{ $user->name }}</b><br>
            {{ $user->email }}
        </div>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}">
            @csrf

            <label for="password">New Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">

            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

            <button type="submit">Set Password</button>
        </form>

        <p class="hint">This link expires for your security. If it no longer works, request a new one.</p>
    </div>
</body>
</html>
