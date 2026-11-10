<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
        <title>Forbidden - {{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { font-family: 'Outfit', sans-serif; }
        </style>
    </head>
    <body class="bg-[#0f172a] text-white h-screen flex items-center justify-center p-6 selection:bg-blue-500/30">
@if(($gtmId ?? null))
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@endif

        <div class="max-w-md w-full text-center space-y-8 animate-in fade-in zoom-in duration-700">
            <!-- Icon -->
            <div class="relative inline-block">
                <div class="absolute inset-0 bg-blue-600 rounded-full blur-3xl opacity-20 animate-pulse"></div>
                <div class="relative bg-slate-800/50 border border-slate-700 w-24 h-24 rounded-3xl flex items-center justify-center mx-auto shadow-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
            </div>

            <!-- Content -->
            <div class="space-y-3">
                <p class="text-blue-500 font-bold text-xs uppercase tracking-[0.3em]">{{ __('Error 403') }}</p>
                <h1 class="text-3xl font-black tracking-tight text-white uppercase">{{ __('Access Restricted') }}</h1>
                <p class="text-slate-400 font-medium text-sm leading-relaxed max-w-[280px] mx-auto">
                    {{ __('You do not have permission to access this area of the system. Contact your administrator.') }}
                </p>
            </div>

            <!-- Action -->
            <div class="pt-6">
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs uppercase tracking-[0.2em] rounded-2xl shadow-xl shadow-blue-900/20 transition-all active:scale-95 border-none">
                    {{ __('Back to Dashboard') }}
                </a>
            </div>
            
            <p class="text-[9px] font-bold text-slate-600 uppercase tracking-widest pt-12">
                {{ config('app.name') }} &bull; {{ __('Pricing Platform') }}
            </p>
        </div>
    </body>
</html>
