@php
    // Finds public/images/<name>.<any extension>, so .png/.jpg/.webp all work.
    $img = fn (string $name) => ($f = glob(public_path("images/{$name}.*"))[0] ?? null) ? asset('images/'.basename($f)) : null;
    $bg = $img('psumaincampus');
    $logo = $img('psu-logo');
    $wave = $img('top-wave');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pangasinan State University · University Calendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Libre+Baskerville:wght@700&family=UnifrakturCook:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/psu-landing.css') }}?v={{ @filemtime(public_path('css/psu-landing.css')) }}">
</head>
<body>

<header class="hero" @if($bg) style="--photo:url('{{ $bg }}')" @endif>
    @if($wave)<img class="wave" src="{{ $wave }}" alt="">@endif
    <div class="hero__in">
        @if($logo)<img class="hero__logo" src="{{ $logo }}" alt="Pangasinan State University seal">@endif
        <div>
            <h1>Pangasinan State University</h1>
            <p class="tag">Region’s Premier University of Choice</p>
            <div class="btns">
                <button type="button" class="btn gold" id="open-login">Calendar Office Log In</button>
                <a class="btn blue" href="https://main.psu.edu.ph" target="_blank" rel="noopener">Visit Pangasinan State University’s Main Website</a>
            </div>
        </div>
    </div>
    <a class="down" href="#contact" aria-label="Scroll down">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </a>
</header>

<dialog id="login-dialog" aria-labelledby="login-title">
    <div class="dlg">
        <button type="button" class="x" id="close-login" aria-label="Close">×</button>
        <h2 id="login-title">Calendar Office Log In</h2>
        <p>Choose your office to continue.</p>
        <a class="opt" href="{{ route('office.login') }}">
            <span class="ic">U</span>
            <span><b>University Offices</b><small>Admin · Submit and track event requests</small></span>
            <span class="go">→</span>
        </a>
        <a class="opt" href="{{ route('planning_office.login') }}">
            <span class="ic">P</span>
            <span><b>Planning Office</b><small>Superadmin · Review requests and manage the calendar</small></span>
            <span class="go">→</span>
        </a>
    </div>
</dialog>

<footer id="contact"><div class="wrap">
    <div class="fgrid">
        <div>
            <div class="fb">Pangasinan State University</div>
            <p>University Calendar Planning and Scheduling Management System</p>
        </div>
        <div>
            <h4>Contact</h4>
            <ul>
                <li>Alvear St., Poblacion, Lingayen, 2401 Pangasinan</li>
                <li>Phone: (075) 632-6513</li>
                <li>Office hours: Monday–Friday, 8:00 AM–5:00 PM</li>
            </ul>
        </div>
        <div>
            <h4>Online</h4>
            <ul>
                <li><a href="https://main.psu.edu.ph" target="_blank" rel="noopener">main.psu.edu.ph</a></li>
            </ul>
        </div>
    </div>
    <div class="legal"><span>© {{ date('Y') }} Pangasinan State University. All rights reserved.</span><span>UniCalendar</span></div>
</div></footer>

<script>
const dlg = document.getElementById('login-dialog');
document.getElementById('open-login').addEventListener('click', () => dlg.showModal());
document.getElementById('close-login').addEventListener('click', () => dlg.close());
// Click on the dark backdrop closes it (clicks inside the card never reach the dialog itself)
dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
</script>
</body>
</html>