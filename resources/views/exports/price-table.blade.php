<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tabela de Preços - {{ $date }}</title>
    <style>
        @page { margin: 0.8cm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            font-size: 9px;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }
        .container { width: 100%; }
        .country-card { margin-bottom: 25px; page-break-inside: avoid; }
        
        /* HEADER BOX SYSTEM */
        .layout-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-bottom: 20px;
        }
        .box {
            border: 1px solid #eef2f6;
            border-radius: 20px;
            padding: 15px 20px;
            background-color: #fafcfe;
            vertical-align: middle;
        }
        .box-left { width: 32%; }
        .box-right { width: 62%; }

        .small-label {
            font-size: 7px;
            font-weight: bold;
            color: #94a3b8;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 8px;
        }

        /* PAIS SECTION */
        .pais-info { width: 100%; }
        .pais-info img {
            width: 35px;
            height: auto;
            border-radius: 3px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 12px;
        }
        .pais-info h1 {
            display: inline-block;
            vertical-align: middle;
            font-size: 28px;
            color: #1e293b;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: -1px;
        }

        /* LEGENDA SECTION */
        .legenda-table { width: 100%; border-collapse: collapse; }
        .legenda-grid { width: 65%; }
        .legenda-notes {
            width: 35%;
            border-left: 2px solid #f1f5f9;
            padding-left: 15px;
            font-size: 7px;
            color: #64748b;
            font-weight: bold;
        }
        .legenda-item {
            font-size: 8px;
            font-weight: bold;
            padding: 5px 0;
            vertical-align: middle;
        }
        .ico {
            width: 12px;
            height: 12px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
        }

        /* TABLE SECTION */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            padding: 0 12px;
        }
        .data-table th {
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1e293b;
        }
        .data-table td {
            padding: 9px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .product-name { font-weight: bold; color: #1e293b; font-size: 10px; }
        .price-val { font-weight: bold; text-align: right; color: #1e293b; font-family: 'Courier'; font-size: 11px;}
        .variation-val { font-weight: bold; text-align: right; font-size: 10px; }
        .status-cell { text-align: right; width: 25px; padding-right: 5px; }

        .up { color: #e11d48; }
        .down { color: #10b981; }
        .neutral { color: #94a3b8; }
        .new { color: #f59e0b; }

        .footer {
            margin-top: 30px;
            font-size: 8px;
            color: #cbd5e1;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        // Ícones em SVG Base64 para garantir renderização perfeita no PDF
        $icoDown = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiMxMGI5ODEiIHN0cm9rZS13aWR0aD0iMyIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMTIgNXYxNE0xOSAxMmwtNyA3LTctNyIvPjwvc3ZnPg==';
        $icoUp = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNlMTFkNDgiIHN0cm9rZS13aWR0aD0iMyIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMTIgMTlWNU01IDEybDctNyA3IDciLz48L3N2Zz4=';
        $icoNeutral = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM5NGEzYjgiIHN0cm9rZS13aWR0aD0iNCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI4Ii8+PGxpbmUgeDE9IjgiIHkxPSIxMiIgeDI9IjE2IiB5Mj0iMTIiLz48L3N2Zz4=';
        $icoStar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSIjZjU5ZTBiIiBzdHJva2U9IiNmNTllMGIiIHN0cm9rZS13aWR0aD0iMSIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cG9seWdvbiBwb2ludHM9IjEyIDIgMTUgOC41IDIyIDkuMiAxNyAxNC4xIDE4LjUgMjEgMTIgMTcuOCA1LjUgMjEgNyAxNC4xIDIgOS4yIDkgOC41IDEyIDIiLz48L3N2Zz4=';
    @endphp

    <div class="container">
        @foreach($exportData as $country)
            <div class="country-card">
                <table class="layout-table">
                    <tr>
                        <td class="box box-left">
                            <span class="small-label">PAÍS DE ORIGEM</span>
                            <div class="pais-info">
                                @if($country->flag)
                                    <img src="{{ $country->flag }}">
                                @endif
                                <h1>{{ $country->name }}</h1>
                            </div>
                        </td>

                        <td class="box box-right">
                            <span class="small-label">LEGENDA DE VARIAÇÃO</span>
                            <table class="legenda-table">
                                <tr>
                                    <td class="legenda-grid">
                                        <table style="width: 100%;">
                                            <tr>
                                                <td class="legenda-item down">
                                                    <img src="{{ $icoDown }}" class="ico"> PREÇO CAIU
                                                </td>
                                                <td class="legenda-item up">
                                                    <img src="{{ $icoUp }}" class="ico"> PREÇO SUBIU
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="legenda-item neutral">
                                                    <img src="{{ $icoNeutral }}" class="ico"> SEM ALTERAÇÕES
                                                </td>
                                                <td class="legenda-item new">
                                                    <img src="{{ $icoStar }}" class="ico"> PRODUTO NOVO
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="legenda-notes">
                                        <p style="margin: 0 0 5px 0;">• EM RELAÇÃO AO PREÇO ANTERIOR</p>
                                        <p style="margin: 0 0 5px 0;">• PREÇOS EM DÓLAR P/ TON 1XFCL 40'</p>
                                        <p style="margin: 0;">• PREÇOS À CONFIRMAÇÃO FINAL</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50%">PRODUTO</th>
                            <th style="text-align: right; width: 15%">ÚLTIMO PREÇO</th>
                            <th style="text-align: right; width: 15%">PREÇO ANTERIOR</th>
                            <th style="text-align: right; width: 15%">VARIAÇÃO</th>
                            <th style="width: 5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($country->products as $product)
                            <tr>
                                <td class="product-name">{{ $product->name }}</td>
                                <td class="price-val">{{ number_format($product->latestPrice, 2, '.', ',') }}</td>
                                <td class="price-val" style="color: #cbd5e1">{{ number_format($product->previousPrice, 2, '.', ',') }}</td>
                                <td class="variation-val @if($product->status == 'up') up @elseif($product->status == 'down') down @endif">
                                    {{ ($product->variation > 0 ? '+' : '') }}{{ number_format($product->variation, 2) }}%
                                </td>
                                <td class="status-cell">
                                    @if($product->status == 'up')
                                        <img src="{{ $icoUp }}" style="width: 10px; height: 10px;">
                                    @elseif($product->status == 'down')
                                        <img src="{{ $icoDown }}" style="width: 10px; height: 10px;">
                                    @elseif($product->status == 'new')
                                        <img src="{{ $icoStar }}" style="width: 10px; height: 10px;">
                                    @else
                                        <img src="{{ $icoNeutral }}" style="width: 10px; height: 10px;">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        <div class="footer">
            Gerado em {{ $date }} | JR Spice - BI Dashboard
        </div>
    </div>
</body>
</html>
