<style>
    @font-face {
        font-family: ReportTemplateTimes;
        font-style: normal;
        font-weight: normal;
        src: url("{{ $templateFonts['timesNewRoman'] }}") format("truetype");
    }
    @font-face {
        font-family: ReportTemplateOldEnglish;
        font-style: normal;
        font-weight: normal;
        src: url("{{ $templateFonts['oldEnglish'] }}") format("truetype");
    }
</style>

<header class="report-letterhead">
    <img class="report-letterhead__seal" src="{{ public_path('images/psu-logo.png') }}" alt="">
    <div class="report-letterhead__identity">
        <span class="report-letterhead__republic">Republic of the Philippines</span>
        <strong>Pangasinan State University</strong>
        <span class="report-letterhead__campus">LINGAYEN CAMPUS</span>
        <span class="report-letterhead__address">Alvear Street, Poblacion, Lingayen, Pangasinan</span>
        <span class="report-letterhead__contacts">
            <img src="{{ public_path('images/psu-report-contact-icon-3.png') }}" alt=""> www.psu.edu.ph
            <img src="{{ public_path('images/psu-report-contact-icon-2.png') }}" alt=""> president@psu.edu.ph
            <img src="{{ public_path('images/psu-report-contact-icon-1.png') }}" alt=""> www.facebook.com/PSUroars
        </span>
    </div>
    <img class="report-letterhead__bagong" src="{{ public_path('images/bagong-pilipinas-report-template.png') }}" alt="">
    <span class="report-letterhead__rule"></span>
</header>

<footer class="report-letterfoot">
    <img class="report-letterfoot__banner" src="{{ public_path('images/psu-report-footer-banner.png') }}" alt="">
    <img class="report-letterfoot__logos" src="{{ public_path('images/psu-report-footer-logos.png') }}" alt="">
</footer>
