@props([
    'title', 'portal', 'action',
    'heading' => null, 'blurb' => null, 'points' => [],
    'otherText' => null, 'otherLabel' => null, 'otherUrl' => null,
])
@php
    // Finds public/images/<name>.<any extension>, so .png/.jpg/.webp all work.
    $img = fn (string $name) => ($f = glob(public_path("images/{$name}.*"))[0] ?? null) ? asset('images/'.basename($f)) : null;
    $bg = $img('psumaincampus');
    $logo = $img('psu-logo');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · UniCalendar · PSU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/psu-login.css') }}?v={{ @filemtime(public_path('css/psu-login.css')) }}">
</head>
<body>
<main class="login">
    <section class="hero" @if($bg) style="--hero:url('{{ $bg }}')" @endif aria-label="About this portal">
        <div class="hero__copy">

            @if($heading)<p class="hero__title">{{ $heading }}</p>@endif
            @if($blurb)<p class="hero__blurb">{{ $blurb }}</p>@endif
            @if (count($points))
                <ul class="points">@foreach ($points as $p)<li>{{ $p }}</li>@endforeach</ul>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="panel__inner">
            @if($logo)<img class="seal" src="{{ $logo }}" alt="Pangasinan State University seal" width="96" height="96">@endif
            <p class="brand">Pangasinan State University</p>
            <p class="badge">{{ $portal }}</p>
            <h1 class="sr-only">Sign in to {{ $portal }}</h1>

            @if ($errors->any())
                <div class="alert" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form id="login-form" method="POST" action="{{ $action }}" novalidate>
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <div class="control">
                        <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <input id="email" name="email" type="email" inputmode="email" value="{{ old('email') }}" required autofocus autocomplete="username" autocapitalize="off" spellcheck="false" placeholder="office@psu.edu.ph" @error('email') aria-invalid="true" @enderror>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="control">
                        <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="toggle" id="toggle-pw" aria-label="Show password" aria-pressed="false">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <label class="remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in on this device</label>
                <button type="submit" class="btn" id="submit-btn"><span>Sign in</span></button>

                @if ($otherText && $otherLabel && $otherUrl)
                    <p class="auth-switch">
                        {{ $otherText }}
                        <a href="{{ $otherUrl }}">{{ $otherLabel }}</a>
                    </p>
                @endif
            </form>
        </div>
    </section>
</main>
<script>
(() => {
    const pw = document.getElementById('password'), t = document.getElementById('toggle-pw');
    if (pw && t) {
        t.addEventListener('click', () => {
            const show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            t.setAttribute('aria-pressed', show);
            t.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }

    const f = document.getElementById('login-form'), b = document.getElementById('submit-btn');
    if (f && b) {
        f.addEventListener('submit', () => {
            b.disabled = true;
            b.classList.add('is-loading');
            b.setAttribute('aria-busy', 'true');
        });
        addEventListener('pageshow', e => {
            if (e.persisted) {
                b.disabled = false;
                b.classList.remove('is-loading');
                b.removeAttribute('aria-busy');
            }
        });
    }
})();
</script>
</body>
</html>