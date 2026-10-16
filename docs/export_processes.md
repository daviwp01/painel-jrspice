# Gestão de Clientes — Contratos de Exportação

Módulo que substitui a planilha `contratos/Painel de dados ATUAL.xlsx` (aba **Painel de info Vendas**).
Documentação de origem dos dados: [`docs/analise-planilha-contratos.md`](analise-planilha-contratos.md).

## 1. Visão geral

O módulo gerencia **contratos** de exportação. Cada contrato tem um cabeçalho (empresas, país,
vendedor, status, logística, pagamentos) e um ou mais **itens** (registro, produto, quantidade,
preço, comissão). A listagem exibe **uma linha por item**, com a nomenclatura exata da planilha.

## 2. Modelo de dados

| Tabela | Responsabilidade |
|--------|------------------|
| `contracts` | Cabeçalho do contrato |
| `contract_items` | Itens (produtos) do contrato |
| `contract_products` | Catálogo de produtos de contrato (próprio, não usa `products` do painel de preços) |
| `contract_users` | Usuários com acesso ao contrato (portal "Meus Produtos") |
| `contract_documents` | Documentos anexados ao contrato |
| `contract_reviews` | Avaliações do contrato (1 por usuário) |

### 2.1 Relacionamentos (`App\Models\Contract`)

- `exporter_id` → `Client` (type `exporter`)
- `importer_id` → `Client` (type `importer`)
- `seller_id` → `User`
- `items()` → `ContractItem` (ordenado por `position`)
- `users()` → `User` (pivot `contract_users`)
- `documents()`, `reviews()`

### 2.2 Campos derivados (nunca digitados)

| Campo | Regra |
|-------|-------|
| `contract_items.sales_usd` | `Quantidade × Preço Ton US$` |
| `contract_items.estimated_euro` | `Comissão US$ × Taxa de Câmbio` |
| `contract_items.commission_usd` | `Venda US$ × commission_rate / 100` (ou valor fixo quando não há taxa) |
| `contract_items.to_pay_usd` | `Venda US$ × to_pay_rate / 100` (ou valor fixo) |
| `Venda Anual US$` (listagem) | `SUM(sales_usd) OVER (ORDER BY date, contract_id, position)` |
| `TT Comissão US$` (listagem) | `SUM(commission_usd) OVER (ORDER BY date, contract_id, position)` |

Os dois acumulados são calculados em tempo de consulta (`ContractQueryService`) — não são
persistidos, o que elimina a corrupção das cadeias de fórmula da planilha.

### 2.3 Overrides por item

`etd_date`, `eta_date`, `dhl_number`, `status_date`, `video_date`, `video_reference` e
`observations` existem tanto no contrato quanto no item. Na listagem o valor exibido é
`COALESCE(item, contrato)` — igual à planilha, onde a coluna só é mesclada quando o valor é igual.

## 3. Nomenclatura (idêntica à planilha)

`Data` · `Contrato` · `Registro` · `Exportador` · `Importador` · `País` · `Produto` ·
`Quantidade` · `Preço Ton US$` · `Venda US$` · `Venda Anual US$` · `Comissão US$` ·
`TT Comissão US$` · `Taxa de Câmbio` · `Estimado em Euro` · `Data Estimada Recebimento` ·
`Vendedor` · `A pagar` · `Recebimento` · `Pago em` · `Pago em Real` · `Incidente` ·
`Video enviado` · `DATA VÍDEO` · `STATUS` · `Data Status` · `Data DHL` · `Data embarque ETD` ·
`Data da chegada ETA` · `DHL Nº` · `Observações`

## 4. STATUS

Lista fechada em `App\Support\ContractStatus`, aplicada no back-end (Form Request) e no front:

`A embarcar dia` · `Transbordo até` · `Chegou porto dia` · `Só faltando comissão` ·
`Invoice ENVIADA` · `Processo FINALIZADO`

## 5. Back-end

| Camada | Arquivo |
|--------|---------|
| Controller | `App\Http\Controllers\ContractController` (index, show, store, update, updateStatus, destroy, bulkDestroy, bulkSyncUsers) |
| Form Requests | `ContractFormRequest` (base), `StoreContractRequest`, `UpdateContractRequest`, `UpdateContractStatusRequest` |
| Query | `App\Services\Contracts\ContractQueryService` (listagem achatada + acumulados + filtros) |
| Resumo | `App\Services\Contracts\ContractSummaryService` |
| Persistência | `App\Services\Contracts\ContractSaver` (transacional, contrato + itens) |
| Catálogo | `App\Services\Contracts\ContractProductCatalog` (resolve/cria produto normalizado) |
| Portal | `App\Services\Contracts\ClientContractService` |
| Importação | `App\Services\Contracts\Spreadsheet\{Reader,Importer,ValueNormalizer}` + comando `contracts:import` |

### 5.1 Rotas

Todas as rotas de contrato exigem `auth` + `EnsureUserIsMaster`:

```
GET    /admin/clients                    admin.clients.index
GET    /admin/contracts/{contract}       contracts.show      (JSON para o slide-over)
POST   /admin/contracts                  contracts.store
PUT    /admin/contracts/{contract}       contracts.update
PATCH  /admin/contracts/{contract}/status contracts.status    (só altera status)
DELETE /admin/contracts/{contract}       contracts.destroy
POST   /admin/contracts/bulk-delete      contracts.bulk-delete
POST   /admin/contracts/bulk-sync-users  contracts.bulk-sync-users
```

**Regra importante:** `contracts.status` altera apenas `status`/`status_date` e **nunca** toca nos
vínculos de usuários. `contracts.update` só substitui os vínculos quando `user_ids` é enviado.

## 6. Importação da planilha

```bash
php artisan contracts:import [caminho] [--dry-run] [--clear]
```

- Idempotente: contratos são casados por `contracts.source_row` (linha da planilha).
- `--dry-run` executa tudo dentro de uma transação e desfaz.
- `--clear` remove os contratos importados (`source_row IS NOT NULL`) antes de importar.
- Relatório completo em `storage/logs/contracts-import-report.json`, incluindo:
  linhas descartadas, linhas de resíduo de fórmula, grafias unificadas, valores revisados,
  **possíveis variantes** (sinalizadas, nunca fundidas automaticamente) e vendedores sem
  usuário correspondente.

Regras de saneamento (`ValueNormalizer`):

| Situação na planilha | Tratamento |
|----------------------|------------|
| País com caixa/acento diferente (`China`/`china`, `Peru`/`Perú`) | Unificado no valor canônico |
| País que é nome de empresa (`Expin`) | `NULL` + reportado |
| Importador com serial de data (ex.: `45650`) | `NULL` + reportado |
| `DHL Nº` contendo data | Convertida para `YYYY-MM-DD` + reportado |
| `Data DHL` = `...` / `CONCLUÍDO` / `não se aplica` | `NULL` |
| `DATA VÍDEO` = `Embarcado` / zero (1899) | `NULL` |
| `Incidente` = `0` | `NULL` |
| `Video enviado` com texto/rastreio | Guardado em `video_reference` |
| STATUS fora da lista oficial | `NULL` + reportado |
| Variante provável (`Honest` ~ `Honest Food`) | Mantida separada + listada para revisão |

## 7. Front-end (Vue 3 + Inertia + Tailwind)

- **`resources/js/Pages/Admin/Clients/Index.vue`** — orquestrador.
- **`resources/js/Pages/Contracts/Partials/ContractStats.vue`** — Volume Total, Comissões
  Pendentes, Atrasos Logísticos, Contratos Ativos.
- **`resources/js/Pages/Contracts/Partials/ContractsTable.vue`** — 31 colunas (todas as da
  planilha), configuráveis por usuário (localStorage), seleção múltipla por contrato,
  seletor de STATUS inline.
- **`resources/js/Pages/Contracts/Partials/ContractSlideOver.vue`** — editor em 3 abas
  (Contrato & Itens · Financeiro · Logística & Status) com lista de itens editável,
  cálculos automáticos e overrides logísticos por item.
- **Portal** (`MyProducts/*`) — lista contratos vinculados ao usuário e detalha itens/documentos.

## 8. Validação pós-importação

Dois verificadores permanentes para rodar sempre que a planilha for reimportada:

```bash
# Planilha × banco: nenhum dado pode se perder (16.976 verificações)
python3 scripts/validate_sheet_import.py

# Banco × payload do formulário de edição: nenhum campo em branco fantasma
php scripts/validate_contract_payload.php
```

| Verificador | O que prova | Resultado em 2026-10-15 |
|---|---|---|
| `validate_sheet.py` | Cada célula de logística/documentação da planilha existe no sistema com o mesmo valor | 16.976 checks · 0 divergências · 1 bloco não importado (linha 2144, sem produto) |
| `validate_contract_payload.php` | Todo campo preenchido no banco chega preenchido no formulário de edição | 1.290 contratos / 2.128 itens · 0 campos ausentes · 0 em branco · 0 divergências |

Regras de saneamento aplicadas a datas (`ValueNormalizer::date`): janela de serial Excel
20000–60000 **e** ano entre 1990–2100, em qualquer tipo de entrada — assim artefatos de
fórmula como `=Data Status - 7` sobre célula vazia (1899-12-23, 1900-01-07) nunca chegam ao
banco, mesmo quando o driver devolve um `DateTime` em vez de um número.

## 9. Ordenação

A listagem aceita ordenação por **`date`** (lista branca em `ContractQueryService::SORTABLE`;
qualquer outra coluna é ignorada e cai no padrão `desc`). O estado vai na URL
(`sort_by` / `sort_dir`), sobrevive à troca de filtros e é destacado no cabeçalho da coluna.

Os acumulados `Venda Anual US$` e `TT Comissão US$` continuam calculados na ordem
cronológica (`SUM() OVER (ORDER BY date, contract_id, position)`), independentemente da
ordem de exibição — são uma propriedade da linha no tempo, não da tela.

## 10. Checklist de aceite

- [x] Nenhum campo da aba "Painel de info Vendas" sem representação no sistema.
- [x] Rótulos idênticos aos cabeçalhos da planilha.
- [x] Os 6 status, com as grafias exatas, validados no back-end.
- [x] Rotas de escrita restritas a `master` (verificado ao vivo: 403).
- [x] Mudança de status não altera vínculo de clientes (verificado ao vivo).
- [x] Busca e filtros sem vazamento (consulta agrupada + teste).
- [x] 1.284 contratos / 2.122 itens importados com relatório de pendências.
- [x] Importação idempotente (reimportar atualiza, não duplica).
- [x] Migração com `down()` funcional (rollback validado no MySQL).
- [x] 23 testes de regressão (`tests/Feature/Contracts`).

---
*Documentação gerada para manutenção do módulo de contratos.*
