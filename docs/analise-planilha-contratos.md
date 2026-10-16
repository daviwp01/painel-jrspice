# Levantamento Completo — Planilha de Contratos × Sistema

**Fonte:** `contratos/Painel de dados ATUAL.xlsx` (1,1 MB, 8 abas)
**Objetivo:** substituir a planilha pelo módulo **Gestão de Clientes** (`/admin/clients`), mantendo 100% da informação e a mesma nomenclatura.

---

## 1. Visão geral das abas

| # | Aba | Conteúdo | Escopo desta etapa |
|---|-----|----------|--------------------|
| 1 | **Painel de info Vendas** | Base de dados dos **contratos** (2.142 linhas, 1.280 contratos, 2017→2026) | **SIM** |
| 2 | **Score** | Tabelas dinâmicas (pivots) derivadas: Score Fornecedor e Score Cliente | Derivável no sistema |
| 3 | **Painel de info Financeiro** | Extrato bancário (2.249 lançamentos: Data, Extrato, Ref/Plano de contas, Entrada, Saída, Saldo, Tipo) | Não — módulo financeiro |
| 4 | **Pontos de Controle** | KPIs: relatório anual de vendas, relatório anual financeiro, positividade e trimestres (comissão USD, câmbio USD→EUR, GAP) | Derivável |
| 5 | **Vendas 26** / **Vendas 25** | Vendas mensais por empresa (Sacconi, Brazilcoa, Opulente) em Kg/Ton, R$, LC R$, comparação ano a ano, lucratividade e performance por país | Não — receitas BR/PT |
| 6 | **JR-BR** / **JR-PT** | DRE por plano de contas (receitas/despesas mensais, 2 empresas) | Não — contabilidade |

> **Conclusão:** só a aba **Painel de info Vendas** alimenta o módulo de contratos. As demais são relatórios derivados ou financeiro — podem virar *dashboards* no sistema depois.

---

## 2. Aba "Painel de info Vendas" — estrutura

- **Títulos (linha 1):** `A1 = "Painel de Informações e dados"` (col. A–V) e `W1 = "Acompanhamento da documentação"` (col. W–AE).
- **Cabeçalhos (linha 2):** 31 colunas, `A2:AE2`.
- **Dados:** linhas **3 → 2144** (2.142 linhas, sem buracos). Filtro automático `A2:AE2144`.
- **Linhas ocultas:** 1.980 (exatamente os contratos `Processo FINALIZADO`) — o funcionário oculta para focar nos ativos.
- **Colunas ocultas (com agrupamento/outline):** M a V — TT Comissão, Taxa de Câmbio, Estimado em Euro, Data Est. Recebimento, Vendedor, A pagar, Recebimento, Pago em, Pago em Real, Incidente.
- **Lista suspensa de STATUS** (`BC2:BC7`), aplicada à coluna Y:
  `A embarcar dia` · `Transbordo até` · `Chegou porto dia` · `Só faltando comissão` · `Invoice ENVIADA` · `Processo FINALIZADO`
- **Linhas mescladas (765):** quase todas nas colunas de documentação (W–AE), agrupando valores iguais por contrato.

### 2.1 Dicionário de colunas (nomenclatura EXATA a replicar no sistema)

| Col | Cabeçalho exato | Nível | Tipo observado | Regra de cálculo na planilha |
|-----|-----------------|-------|----------------|------------------------------|
| A | `Data` | contrato | data | — |
| B | `Contrato` | contrato | **texto** (`848A`, `833-2`, `799-2`, `1010-1`…) | — |
| C | `Registro` | **linha** | texto/nº | — |
| D | `Exportador` | contrato | texto (99 valores) | — |
| E | `Importador` | contrato | texto (52 valores) | — |
| F | `País` | contrato | texto (23 valores) | — |
| G | `Produto` | **linha** | texto (374 crus / 329 normalizados) | — |
| H | `Quantidade` | **linha** | número (toneladas) | — |
| I | `Preço Ton US$` | **linha** | número | — |
| J | `Venda US$` | **linha** | número | `=Quantidade × Preço Ton` |
| K | `Venda Anual US$` | corrido | número | `=K(anterior) + J` — **acumulado global desde 2017** (não reseta por ano) |
| L | `Comissão US$` | **linha** | número | `=Venda × %` → **1% (7), 1,5% (21), 2% (1.495), 2,5% (3), 3% (499), 4% (25)** + 11 linhas com valor fixo (`=Qtd × 50/60/75/130/140`) |
| M | `TT Comissão US$` | corrido | número | `=M(anterior) + L` — acumulado global |
| N | `Taxa de Câmbio` | **linha** | número (0,89 / 0,91 / 0,94) | — |
| O | `Estimado em  Euro` | **linha** | número | `=Comissão × Taxa de Câmbio` |
| P | `Data Estimada Recebimento` | **linha** | data | — |
| Q | `Vendedor` | contrato | texto (11 valores) | — |
| R | `A pagar` | **linha** | número | `=Venda × %` → 0,9% (35), 1% (7), 1,35% (9) — comissão a pagar ao vendedor |
| S | `Recebimento` | contrato | data | — |
| T | `Pago em` | contrato | data | — |
| U | `Pago em Real` | contrato | número (BRL) | — |
| V | `Incidente` | contrato | texto livre | — |
| W | `Video enviado` | **linha** | texto livre (nº de rastreio, "ok", data…) | — |
| X | `DATA VÍDEO` | **linha** | data **ou texto** | fórmula `=Data Status − 7` em algumas linhas |
| Y | `STATUS` | contrato | lista dos 6 valores | — |
| Z | `Data Status` | contrato | data | — |
| AA | `Data DHL` | contrato | data **ou** `...` **ou** `CONCLUÍDO` **ou** `não se aplica` | fórmula `=IF(STATUS="Transbordo até"; Data Status − 10; "não se aplica")` |
| AB | `Data embarque ETD` | contrato* | data | — |
| AC | `Data da chegada ETA` | contrato* | data | — |
| AD | `DHL Nº` | **linha** | texto | — |
| AE | `OBSERVAÇÃO MAZINHO` (exibido como **Observações**) | contrato | texto livre (controle de baixa: "recebido", "pagou", "cancelado"…) | — |

\* AB/AC/AD são **predominantemente** por contrato, mas divergem dentro do bloco em 52/53/110 blocos respectivamente (itens embarcados em lotes diferentes).

### 2.2 Granularidade real: contrato × itens

- **1.284 blocos** de contrato · **1.280 números distintos** · **4 números repetidos** (325, 401, 650, 1370 — datas diferentes → contratos distintos).
- Tamanho dos blocos: 1 item = 858 · 2 = 246 · 3 = 82 · 4 = 39 · 5 = 18 · 6 = 19 · 7 = 12 · 8 = 3 · 9 = 2 · 10 = 4 · **20 = 1** (contrato 1370).
- Média: **1,65 itens por contrato**.
- **Itens por ano:** 2017: 2 · 2018: 15 · 2019: 55 · 2020: 146 · 2021: 123 · 2022: 145 · 2023: 192 · 2024: 226 · 2025: 196 · 2026: 180.
- Linhas por ano: 2017: 4 · 2018: 17 · 2019: 81 · 2020: 223 · 2021: 199 · 2022: 227 · 2023: 307 · 2024: 395 · 2025: 356 · 2026: 313.

**Campos de cabeçalho (iguais em todas as linhas do contrato):** Data, Exportador, Importador, País, Vendedor, STATUS, Data Status.
**Campos de item:** Registro, Produto, Quantidade, Preço Ton, Venda, Comissão, Estimado Euro, A pagar, DHL Nº, Video enviado, DATA VÍDEO.
**Campos mistos (cabeçalho com exceções por item):** ETD, ETA, Data DHL, Data Status, DATA VÍDEO, Recebimento/Pago em/Pago em Real, Incidente, Observações.

### 2.3 STATUS (distribuição real)

| STATUS | Linhas |
|--------|-------:|
| Processo FINALIZADO | 1.979 |
| Transbordo até | 60 |
| A embarcar dia | 53 |
| Invoice ENVIADA | 15 |
| Só faltando comissão | 12 |
| Chegou porto dia | 3 |
| **Total ativo (não finalizado)** | **143** |

---

## 3. Problemas e sujeira encontrados na planilha

### 3.1 Linhas órfãs / quebradas
- **Linha 353**: tem `Data`, `Registro`, `Importador`, produto, valores e STATUS — **sem número de contrato** e sem exportador.
- **Linhas 2125–2143 (19 linhas)**: só têm `DATA VÍDEO` (= 23/12/1899 → zero), `STATUS` e `Data DHL` (`...`). São resíduo de fórmula/filtro — **lixo, sem valor de negócio**.
- **Linha 2144**: contrato 1370, mas só com DATA VÍDEO/STATUS/Data DHL.

### 3.2 `Venda Anual US$` e `TT Comissão US$` estão corrompidos
São acumulados globais (`=K anterior + J`), mas a cadeia quebra em **4 pontos** por linhas inseridas sem ajustar a fórmula:

| Linha | Contrato | K anterior | K nesta linha |
|------:|---------:|-----------:|--------------:|
| 327 | 312 | 6.577.979,16 | **828** |
| 1157 | 833-2 | 29.148.628,32 | **1.181,60** |
| 1636 | 1099 | 41.747.181,38 | 41.708.818,88 |
| 1799 | 1190 | 45.728.036,53 | **7.100** |

Consequência: soma real de `Venda US$` = **US$ 53.875.404,78**, mas o último `Venda Anual` = **US$ 7.967.980,75**. A coluna não pode ser importada como verdade — **deve ser recalculada**.

### 3.3 Poluição de tipos (colunas que deveriam ser data e viraram texto)
| Coluna | Situação |
|--------|----------|
| `DATA VÍDEO` | 1.139 linhas com o texto **"Embarcado"**, 870 vazias, só 67 datas reais, 19 zeros (1899), 3 `#VALUE!` |
| `Data DHL` | 3.029 `...`, 1.066 `CONCLUÍDO`, 7 `não se aplica`, resto datas |
| `Data Status` | mistura datas com o texto `CONCLUÍDO` |
| `DHL Nº` | mistura números DHL com datas e `cancelado` |
| `Pago em Real` | mistura valores em R$ com datas e o texto `335 Euros` |
| `Recebimento` | 1 linha com o valor `116` (não é data) |
| `Incidente` | 17 linhas com `0`, 24 com variações de "cancelado", 19 texto útil |
| `Video enviado` | mistura datas, textos ("video ok", "PAGO") e rastreios (`300100/1`) |

### 3.4 Dados mestres sujos (precisam de tratamento na importação)
- **País:** `India/india`, `China/china`, `Peru/Perú`, `Indonesia/Indonésia`, e **`Expin` em 4 linhas** (é importador, não país).
- **Vendedor:** `Mônica/Monica`, ` Paulo Stassi` (espaço inicial).
- **Importador:** `Vida em Grãos/Vida em grãos`, `Emporium/Imporium`, `BLC/BLC Comercial`, `STM/STM Ervas`, e **4 linhas com serial de data no lugar do nome** (45650–45653 = 03/10/2024 e vizinhos — linhas 278–281, contratos 282–285).
- **Exportador:** `Gogoco `/`Gogoco`, `Honest`/`Honest Food`, `Universal`/`Universal Seed`, `ICD Biochem`/`ICD Biochemistry`, `Riddhi Food`/`Riddhi Foods`, `Pooja`/`Pooja Food`, `Caliskan`/`Caliskan Tarim`, `Nutrigredients`/`Nutringredients`, **`CANCELADO` em 3 linhas** (não é fornecedor) e **1 linha com `" "`** (espaço).
- **Produto:** 374 grafias → **329 normalizados**; 37 grupos com variantes de caixa/espaço/acento (ex.: `Curcuma Quebrada 2%`/`Cúrcuma quebrada 2%`, `Paprica Doce Asta 120` com 4 grafias).
- **Números de contrato não numéricos:** `1010-1`, `1010-2`, `1076-B`, `799-2`, `800-2`, `833-2`, `848A` → o campo **precisa ser texto**, não numérico.

### 3.5 Catálogo de produtos incompatível
Apenas **74 dos 374** produtos da planilha existem (normalizados) na tabela `products` do sistema — e essa tabela é o catálogo do **painel de preços**, com hierarquia de safra. **Não deve ser poluída.**

---

## 4. Diagnóstico do sistema atual

### 4.1 Schema `export_processes` × planilha

| Planilha | Sistema atual | Situação |
|----------|---------------|----------|
| Data | `date` | OK |
| Contrato | `contract_number` (string) | OK |
| Registro | `register_number` | OK (mas é por item) |
| Exportador | `exporter_id → clients` | OK |
| Importador | `importer_id → clients` | OK |
| **País** | — | **AUSENTE** |
| Produto | `product_id → products` | **Catálogo errado** (ver 3.5) |
| Quantidade | `quantity_tons` | OK |
| Preço Ton US$ | `price_per_ton_usd` | OK |
| Venda US$ | `sales_usd` | OK (calculado no front) |
| Venda Anual US$ | `annual_sales_usd` | Nome ruim + valores corrompidos |
| Comissão US$ | `commission_usd` | OK (mas **% não é armazenada**) |
| TT Comissão US$ | `total_commission_usd` | Acumulado |
| Taxa de Câmbio | `exchange_rate` | OK |
| Estimado em Euro | `estimated_euro` | OK |
| Data Estimada Recebimento | `estimated_receipt_date` | OK |
| Vendedor | `seller_id → users` | OK |
| A pagar | `to_pay_usd` | OK (**% não armazenada**) |
| Recebimento | `receipt_date` | OK |
| Pago em | `paid_in_date` | OK |
| Pago em Real | `paid_in_brl` | OK |
| Incidente | `incident` | OK |
| Video enviado | `video_sent` (boolean) | **Perde o rastreio/texto** |
| DATA VÍDEO | `video_date` | OK |
| STATUS | `status` (string livre) | **Precisa ser enum dos 6 valores** |
| Data Status | `status_date` | OK |
| Data DHL | `dhl_date` | OK |
| Data embarque ETD | `etd_date` | OK |
| Data da chegada ETA | `eta_date` | OK |
| DHL Nº | `dhl_number` | OK |
| OBSERVAÇÃO MAZINHO → exibido como `Observações` | `observations` | OK |
| — | `shipping_company`, `container_number` | Extra do sistema (manter) |

### 4.2 Bugs críticos encontrados no código

1. **Broken Access Control (GRAVE)** — `routes/web.php:27-31`: as rotas `POST/PUT/DELETE /export-processes` (criar, editar, excluir, bulk-delete, bulk-sync-clients) estão no grupo `['auth','verified','throttle']`, **sem** `EnsureUserIsMaster`. Qualquer usuário autenticado pode manipular contratos via request direto, mesmo sem ver a tela.
2. **Perda silenciosa de vínculos** — `ExportProcessController::update()` sempre chama `syncContractUsers($process, $request->input('client_ids', []))`. O seletor de status inline (`ExportProcessesTable.vue:221`) envia **apenas** `status` → `sync([])` → **desvincula todos os clientes do contrato** a cada mudança de status.
3. **Filtro de busca sem agrupamento** — `index()`: `where(A)->orWhere(B)->orWhereHas(C)` sem `where(function(){...})`; combinado com o filtro de status, o `AND status=` só se aplica à última condição → **vazamento de registros** com status diferente do filtrado.
4. **`has()` em vez de `filled()`** — `$request->has('search')` / `has('status')` retornam `true` para string vazia, gerando `LIKE '%%'` e `status = ''`.
5. **Autorização inconsistente no portal** — `authorizeAccess()` usa o pivot `export_process_users`, mas `downloadDocument()` usa `users.client_id`. Um cliente vinculado via pivot **não consegue baixar** os documentos do próprio contrato.
6. **Resumo recalculado a cada página** — 3 agregações completas (`SUM`, `COUNT`) sobre a tabela inteira em toda requisição, sem cache; `User::get()` e `Product::get()` completos carregados a cada render.
7. **Dados de demonstração em produção** — 6 contratos `JRS-2026-*` fictícios (ids 33–38) e 5 clientes de teste na tabela `clients`.

### 4.3 O que o sistema já faz bem (manter)
- Slide-over com 3 abas (Geral & Produto / Financeiro / Logística & Status).
- Seletor de status inline com cores por status.
- Configuração de colunas por usuário (localStorage).
- Bulk: seleção, exclusão em massa e vínculo em massa de clientes.
- Cálculo automático de `Venda US$ = Qtd × Preço`.
- "Dar baixa automática" de comissão.
- Documentos por contrato (upload/download com storage privado).
- Avaliações de contrato com resposta do admin.

---

## 5. Decisões de arquitetura propostas

### 5.1 Modelo de dados: **Contrato + Itens** (recomendado)
A planilha força o funcionário a repetir cabeçalho em cada linha — e é justamente aí que nascem as inconsistências (contrato sem exportador, país errado, status divergente). O modelo normalizado elimina a causa:

```
contracts          → Data, Contrato, Exportador, Importador, País, Vendedor,
                     STATUS, Data Status, Data DHL, ETD, ETA, DHL Nº,
                     Video enviado, DATA VÍDEO, Incidente, Recebimento,
                     Pago em, Pago em Real, Data Est. Recebimento, Taxa de Câmbio,
                     observations, shipping_company, container_number
contract_items     → Registro, Produto, Quantidade, Preço Ton US$, Venda US$,
                     Comissão US$ + %, Estimado em Euro, A pagar + %,
                     (ETD/ETA/DHL Nº/Data Status/DATA VÍDEO/OBS como override por item)
contract_products  → catálogo próprio de produtos de contrato (não reutiliza `products`)
```
A listagem continua podendo exibir **uma linha por item** (idêntica à planilha) com o cabeçalho expandido.

### 5.2 Nomenclatura
Rótulos de coluna/tela **exatamente** como no cabeçalho da planilha (seção 2.1), incluindo grafia (`Preço Ton US$`, `Estimado em  Euro`, `Data embarque ETD`); a única adaptação é `OBSERVAÇÃO MAZINHO` → `Observações`.

### 5.3 Valores derivados
`Venda US$`, `Venda Anual US$` (acumulado), `TT Comissão US$` (acumulado) e `Estimado em Euro` passam a ser **calculados pelo sistema** (nunca digitados), eliminando a corrupção das cadeias de fórmula.

### 5.4 Importação
Comando `php artisan contracts:import` (idempotente, dry-run, relatório de divergências) que:
- normaliza países/vendedores/exportadores/importadores/produtos;
- descarta as 19 linhas órfãs e sinaliza a linha 353;
- converte `...`/`CONCLUÍDO`/`Embarcado` em booleanos/flags em vez de lixo em colunas de data;
- recalcula os acumulados;
- gera relatório de tudo que não pôde ser mapeado com segurança.

---

## 6. Checklists de aceite

- [x] Nenhum campo da aba "Painel de info Vendas" sem representação no sistema.
- [x] Rótulos idênticos aos cabeçalhos da planilha.
- [x] Os 6 status, com as grafias exatas, em dropdown validado no back-end.
- [x] Rotas de escrita de contrato restritas a `master`.
- [x] Mudança de status não altera vínculo de clientes.
- [x] Busca e filtros sem vazamento (queries agrupadas).
- [x] Importação das 2.142 linhas / 1.284 contratos com relatório de pendências.
- [x] Listagem com 2.000+ registros sem N+1 e com índices adequados.

---

## 7. Resultado da implementação (2026-10-15)

Documentação do módulo: [`export_processes.md`](export_processes.md).

### 7.1 Números finais da importação

| Métrica | Valor |
|---------|------:|
| Contratos importados | 1.284 |
| Itens importados | 2.122 |
| Produtos de contrato criados | 251 (+375 semeados do catálogo de preços) |
| Clientes (exportadores/importadores) criados | 143 |
| Linhas de resíduo de fórmula ignoradas | 740 (linhas 2125–2865) |
| Linhas descartadas com aviso | 2 (linha 353 sem número; bloco da linha 2144 sem produto) |

### 7.2 Conferência linha a linha contra a planilha

- **2.122 de 2.122** itens conferidos por `source_row`: 0 ausentes, 0 extras.
- `Venda US$`: apenas 1 diferença de meio centavo (linha 1841: 32.095,625 → arredondamento
  comercial para 32.095,63).
- `Comissão US$`: 6 diferenças de meio centavo (mesmo motivo).
- Total de vendas importado: **US$ 53.967.824,79** (a planilha somava US$ 53.910.404,78 nos
  valores em cache de `J` porque algumas linhas tinham valor digitado em vez de `=I*H`;
  o sistema recalcula `Quantidade × Preço`, que é a regra da própria planilha).

### 7.3 Bugs corrigidos no sistema

1. **Broken Access Control** — `POST/PUT/DELETE /export-processes` estavam sem
   `EnsureUserIsMaster`. Agora todas as rotas de contrato exigem master (verificado ao vivo: 403).
2. **Perda silenciosa de vínculos** — mudar o status fazia `sync([])` e desvinculava todos os
   clientes. Agora existe `PATCH /admin/contracts/{id}/status` que só toca em `status`/
   `status_date` (verificado ao vivo: vínculo preservado).
3. **Vazamento no filtro de busca** — `orWhere` sem agrupamento fazia o `AND status` se aplicar
   só à última condição. Consulta reagrupada + teste de regressão.
4. **Autorização inconsistente no portal** — `authorizeAccess()` usava o pivot mas
   `downloadDocument()` usava `users.client_id`. Agora todos usam o pivot (teste de regressão).
5. **Resumo recalculado sem índice** — 3 agregações completas por página; agora há índices em
   `contracts(status)`, `contracts(date)`, `contracts(importer_id, date)` etc.
6. **`is_master` ignorado pelo seeder** — o campo não está em `$fillable`, então
   `updateOrCreate` descartava o valor. Seeder corrigido com `forceFill`.

### 7.4 Dados tratados na importação (nada foi descartado sem registro)

| Situação | Ocorrências | Tratamento |
|----------|------------:|------------|
| `DHL Nº` com data no lugar de código | 12 | Convertida para `YYYY-MM-DD` + reportado |
| Importador com serial de data (linhas 278–281) | 4 | `NULL` + reportado |
| País = nome de empresa (`Expin`) | 4 | `NULL` + reportado |
| `Vida em Grãos` / `Vida em grãos` | 33 | Unificado |
| `Monica` / `Mônica` | 115 | Unificado |
| Possíveis variantes de exportador | 8 pares | Mantidas separadas + listadas para revisão |
| Possíveis variantes de importador | 5 pares | Mantidas separadas + listadas para revisão |
| Vendedores sem usuário na plataforma | 8 | `seller_id` vazio + listados |
| Linha 353 (sem número de contrato) | 1 | Importada como contrato avulso + aviso |
| Bloco da linha 2144 (sem produto/quantidade) | 1 | Descartado + aviso |
| Resíduo de fórmula (linhas 2125–2865) | 740 | Ignorado (só `STATUS`/`...`/data zero) |

### 7.5 Verificações executadas

- Migração **ida e volta** no MySQL real (6 contratos legados migrados, pivot e avaliação
  preservados, FKs reapontadas para `contracts`, rollback restaurou o estado legado).
- Importação **idempotente**: reimportar atualiza 1.284 contratos / 2.122 itens sem duplicar.
- **23 testes de regressão** em `tests/Feature/Contracts` (autorização, status sem detach,
  busca sem vazamento, cálculos, forma dos dados, portal).
- Smoke test **ao vivo** contra MySQL: login master → listagem com 1.290 contratos / 2.128 itens
  renderizando; filtros de busca/status corretos; non-master recebe 403 em todas as rotas de
  escrita; PATCH de status preserva vínculos.
- `npm run build` concluído sem erros (chunks `ContractsTable` e `ContractSlideOver` gerados).
