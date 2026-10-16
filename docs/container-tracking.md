# Módulo de Rastreamento Multi-Carrier (Track & Trace)

Arquitetura **Strategy / Adapter** para as 9 armadoras marítimas, com contrato
único de saída e fallback de backend configurável.

## 1. Peças

| Arquivo | Papel |
|---|---|
| `app/Contracts/ContainerTrackerInterface.php` | Contrato que todo driver implementa (`track`, `carrierName`, `carrierCode`) |
| `app/DataTransferObjects/TrackingDataDTO.php` | Saída normalizada + estados (`ok`, `not_configured`, `not_found`, `unsupported`, `error`, `unavailable`) |
| `app/Services/ContainerTracking/ContainerPrefixResolver.php` | Prefixo ISO 6346 (4 letras) → armadora. **32 prefixos**, lidos da config |
| `app/Services/ContainerTracking/TrackingManager.php` | Estende `Illuminate\Support\Manager`; `forContainer()` resolve o driver |
| `app/Services/ContainerTracking/Drivers/AbstractCarrierDriver.php` | Executa `api` / `scraper` / `aggregator` a partir da config |
| `app/Services/ContainerTracking/Drivers/*Driver.php` | 9 adapters: `Maersk`, `CmaCgm`, `Msc`, `HapagLloyd`, `Evergreen`, `Hmm`, `Oocl`, `Cosco`, `Zim` |
| `app/Providers/TrackingServiceProvider.php` | Singletons do resolver e do manager (registrado em `bootstrap/providers.php`) |
| `config/tracking.php` | URLs, chaves, backends, mapa de prefixos, TTL de cache, regex ISO 6346 |
| `app/Http/Controllers/Api/ContainerTrackingController.php` | Endpoints + cache + validação |
| `app/Exceptions/Tracking/*.php` | `TrackingException` (base), `ContainerNotFoundException`, `CarrierNotSupportedException`, `CarrierApiException` |

## 2. Endpoints

```
GET /meus-produtos/{contract}/rastreamento   → usa o container do contrato (autorizado por pivot)
GET /rastreamento/{container}                → consulta direta (regex ^[A-Z]{4}[0-9]{7}$)
```

Ambos com `throttle:30,1` e resposta **sempre** no formato do DTO + `state`.

| Estado | Significado | Front |
|---|---|---|
| `ok` | Consulta concluída | Mostra portos, navio, eventos |
| `unavailable` | Contrato sem `container_number` | "Container não informado" |
| `unsupported` | Prefixo fora do mapa | "Armadora não suportada" |
| `not_configured` | Backend sem URL/chave no `.env` | "Rastreamento indisponível" + motivo |
| `not_found` | Armadora sem registro | "Registro não encontrado" |
| `error` | Falha de rede/HTTP | "Falha na consulta" |

## 3. Cache

`Cache::store(config('tracking.cache.ttl'))`, chave `tracking.container.{carrier}.{md5(container)}`,
TTL padrão **60 min**. **Só sucessos são cacheados** — uma falha ou uma credencial
ausente precisa sumir assim que for corrigida.

## 4. Como ligar uma armadora

Nenhum endpoint está embutido no código: tudo vem do `.env` (exemplo completo em
`.env.example`).

```dotenv
TRACKING_MAERSK_BACKEND=api          # api | aggregator | scraper
TRACKING_MAERSK_URL=https://.../{container}
TRACKING_MAERSK_KEY=...
```

- **`api`** — `GET/POST` na URL com `{container}`, headers/chaves da config, e o
  mapeamento JSON → DTO declarado em `config/tracking.php` (`carriers.*.api.map`,
  com caminhos no formato `data.events`).
- **`aggregator`** — mesmo mecanismo usando `TRACKING_AGGREGATOR_URL`/`_KEY`
  (agregador pago, ex.: JSONCargo / Terminal49).
- **`scraper`** — portal público via `Symfony\Component\DomCrawler\Crawler`
  com seletores CSS declarados em `carriers.*.scraper.selectors`.

Sem backend configurado o driver devolve `not_configured` — **nunca dado inventado**.

## 5. Adicionar prefixo

`config/tracking.php` → `carriers.{x}.prefixes[]`. Valide contra o registro público
do BIC antes de produção. O mapa atual tem 32 códigos; teste `maps more than thirty
prefixes` garante o mínimo.

## 6. Testes

```bash
php artisan test tests/Unit/ContainerPrefixResolverTest.php   # 9 testes: prefixos, normalização, ISO 6346, manager
php artisan test tests/Feature/ContainerTrackingTest.php      # 8 testes: 422, estados, DTO, cache, autorização
```

## 7. Front-end

`MyProducts/Partials/TabTracking.vue` busca o DTO **só quando a aba abre**
(`Show.vue` → `fetchTracking()`), renderiza estados reais e:

- removeu navio/viagem/velocidade/temperatura fabricados por hash;
- usa `origin.port`/`destination.port` quando a transportadora devolve, e cai para
  **país** (rótulo muda de "Porto" para "País") quando não devolve;
- linha do tempo = eventos reais da transportadora, ou marcos do contrato com
  **datas reais** (`—`/`aguardando` quando ausente).

## 8. Dado disponível hoje

| Campo | Contratos preenchidos |
|---|---:|
| `container_number` | preenchido manualmente (a planilha não tinha a coluna) |
| `dhl_number` (documentos) | 903 |
| `etd_date` / `eta_date` | 659 / 642 |

Enquanto `container_number` estiver vazio o endpoint responde `unavailable`,
que é exatamente o estado que o portal mostra.
