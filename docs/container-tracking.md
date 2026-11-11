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
GET /rastreamento/{container}                → consulta direta (aceita container ISO, booking ou B/L — `isValidReference`)
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
- **`aggregator`** — mesmo mecanismo usando `TRACKING_AGGREGATOR_URL`/`_KEY` +
  `TRACKING_AGGREGATOR_DRIVER` (`tracktainer` | `terminal49`). **Ativo: Tracktainer**
  (`TracktainerAggregator` — list-first, POST só se o número for novo; 409 = sem
  crédito). Terminal49 permanece implementado, desativado via env.
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

## 9. Chegada automática (eta_date)

O Tracktainer ainda **não expõe webhook** (docs: “Coming soon… polling com
`filter[updated_after]`”). O caminho oficial é o polling agendado:

| Peça | Papel |
|---|---|
| `TracktainerClient` | HTTP/auth/erros da API |
| `ArrivalDateExtractor` | payload → data de chegada (ACT `ARRI`/`DISCH` > `eta` quando `ARRIVED`/`COMPLETED`) |
| `ContractArrivalSyncService` | casa `container_number` (case-insensitive) e grava `eta_date` **só se mudou** |
| `tracking:sync-arrivals` | pagina por `links.next`, watermark em `settings.tracktainer_sync_after`, `--dry-run` |
| `routes/console.php` | `everyThirtyMinutes()->withoutOverlapping()` (worker já roda `schedule:run`) |

```bash
php artisan tracking:sync-arrivals --dry-run   # mostra o que mudaria
php artisan tracking:sync-arrivals              # aplica e avança o watermark
```

Falha de API **não** avança o watermark (próxima execução repete a janela).

## 10. Containers, bookings e B/L

O portal aceita **qualquer referência de 8–25 caracteres alfanuméricos**
(`ContainerPrefixResolver::isValidReference`):

| Formato | Exemplo | Resolução da armadora |
|---|---|---|
| ISO 6346 (4L+7D) | `ONEU9410240` | prefixo do mapa → driver |
| Booking / B/L | `TAOGQ5798900` | `shipping_company` do contrato; se ausente e Tracktainer ativo → **auto-detect** (detecção assíncrona da API) |
| Numérico | `279066292` | idem (Tracktainer detecta a linha) |

- Tracktainer: `shipment_number_type` = `CONTAINER` se ISO, senão `BOOKING`
- Fallback de auto-detetect só existe quando `TRACKING_AGGREGATOR_DRIVER=tracktainer`
  (senão `unsupported`, como antes)
