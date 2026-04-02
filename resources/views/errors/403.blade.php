<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
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
                <p class="text-blue-500 font-bold text-xs uppercase tracking-[0.3em]">Erro 403</p>
                <h1 class="text-3xl font-black tracking-tight text-white uppercase">Acesso Restrito</h1>
                <p class="text-slate-400 font-medium text-sm leading-relaxed max-w-[280px] mx-auto">
                    Você não possui permissão para acessar esta área do sistema. Entre em contato com seu administrador.
                </p>
            </div>

            <!-- Action -->
            <div class="pt-6">
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs uppercase tracking-[0.2em] rounded-2xl shadow-xl shadow-blue-900/20 transition-all active:scale-95 border-none">
                    Retornar ao Dashboard
                </a>
            </div>
            
            <p class="text-[9px] font-bold text-slate-600 uppercase tracking-widest pt-12">
                {{ config('app.name') }} &bull; Plataforma de Preços
            </p>
        </div>
    </body>
</html>
