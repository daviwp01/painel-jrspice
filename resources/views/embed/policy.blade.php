<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @php $gtmId = app()->environment('production') ? config('app.gtm_container_id') : null; @endphp
    @if($gtmId)
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $gtmId }}');</script>
    <!-- End Google Tag Manager -->
    @endif
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — Xvision</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { margin: 0; background: #f8fafc; color: #0f172a; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        main { max-width: 800px; margin: 0 auto; padding: 3rem 1.5rem; }
        h1 { font-size: 1.5rem; text-transform: uppercase; letter-spacing: -0.02em; }
        article { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 2rem; line-height: 1.7; font-size: 0.95rem; white-space: pre-line; }
        footer { max-width: 800px; margin: 0 auto; padding: 0 1.5rem 3rem; font-size: 0.75rem; color: #94a3b8; }
    </style>
</head>
<body>
@if(($gtmId ?? null))
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@endif

<main>
    <h1>{{ $title }}</h1>
    <article>{{ $body !== '' ? $body : __('Text not yet published.') }}</article>
</main>
<footer>Xvision — xvision.xpice.pt</footer>
</body>
</html>
