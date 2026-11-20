<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set a new password · UniCalendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}
        body{display:grid;min-height:100vh;margin:0;padding:24px;place-items:center;background:#f4f6fb;color:#20243a;font-family:Inter,system-ui,sans-serif}
        main{width:min(100%,460px);padding:30px;border:1px solid #e1e5ef;border-radius:16px;background:#fff;box-shadow:0 12px 32px rgba(27,39,86,.07)}
        h1{margin:0 0 8px;color:#26336b;font-size:22px}
        p{margin:0 0 22px;color:#64708c;font-size:14px;line-height:1.55}
        label{display:block;margin:16px 0 6px;font-size:13px;font-weight:600}
        input{width:100%;padding:11px 12px;border:1px solid #ccd3e2;border-radius:8px;font:inherit}
        button{width:100%;margin-top:22px;padding:12px;border:0;border-radius:8px;background:#0927d8;color:#fff;font:inherit;font-weight:600;cursor:pointer}
        .error{margin:14px 0;padding:10px 12px;border-radius:8px;background:#fff0f0;color:#9b1c1c;font-size:13px}
        form.logout{margin-top:12px}
        form.logout button{margin:0;border:1px solid #ccd3e2;background:#fff;color:#26336b}
    </style>
</head>
<body>
    <main>
        <h1>Set a new password</h1>
        <p>Your Planning Office administrator issued a temporary password. Set a new password to continue using your office account.</p>

        @if($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('office.password.force.update') }}">
            @csrf
            @method('PATCH')
            <label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
            <label for="new_password_confirmation">Confirm new password</label>
            <input id="new_password_confirmation" name="new_password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            <button type="submit">Save new password</button>
        </form>

        <form class="logout" method="POST" action="{{ route('office.logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </main>
</body>
</html>
