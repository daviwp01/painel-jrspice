# Documentação do Módulo de Gestão de Clientes (Exportação)

Este documento descreve a arquitetura, funcionalidades e estrutura do módulo de **Gestão de Clientes** (processos de exportação) criado para o painel JRSpice. Use isso como referência para futuras manutenções.

## 1. Visão Geral
O módulo gerencia os contratos de exportação da empresa. Ele consolida informações **Gerais**, **Financeiras** e de **Logística** em um único registro, permitindo o acompanhamento do fluxo da mercadoria e o status dos pagamentos e comissões.

## 2. Estrutura do Banco de Dados
O módulo depende primariamente do model `ExportProcess` (`export_processes` table) e de relacionamentos com outras tabelas.

### Relacionamentos (`App\Models\ExportProcess`):
- `exporter_id` (BelongsTo `Client` - type: 'exportador')
- `importer_id` (BelongsTo `Client` - type: 'importador')
- `product_id` (BelongsTo `Product`)
- `seller_id` (BelongsTo `User` - usuários que são vendedores)

## 3. Back-end
- **Controller Principal**: `App\Http\Controllers\ExportProcessController`
  - Responsável pelas ações `index` (renderização com cálculo de totais via `$summary`), `store`, `update`, `destroy`.
  - Todas as validações estão agrupadas na função `validateProcess()`.
- **Controller de Dados de Apoio**: `App\Http\Controllers\Admin\DataController`
  - Responsável pelo gerenciamento de cadastros básicos (Clientes Exp/Imp, Produtos, etc.) na "Gestão de Dados".

## 4. Front-end (Vue 3 + Inertia.js + TailwindCSS)
Para garantir manutenibilidade e seguir princípios de _Clean Code_ e arquitetura baseada em componentes, o front-end principal foi fragmentado.

### 4.1. Orquestrador
- **`resources/js/Pages/Admin/Clients/Index.vue`**
  - Componente "Maestro" da Gestão de Clientes.
  - Busca os dados e gerencia o estado principal (ex: `isSlideOverOpen`, `editingProcess`).
  - Menos de 100 linhas. Filtra exportadores e importadores das props via _computed properties_.

### 4.2. Componentes Parciais (Partials)
Localizados em `resources/js/Pages/ExportProcesses/Partials/`:

- **`ExportProcessesStats.vue`**
  - Renderiza os 3 cards do topo: Volume Total Exportado, Comissões Pendentes e Atrasos de Logística.
- **`ExportProcessesTable.vue`**
  - Tabela principal de exibição dos dados.
  - Implementa a função de cores para os Status (`getStatusColor`) e sinaliza a presença de Incidentes e Vídeos.
  - Emite os eventos `@create` e `@edit` para o Maestro.
- **`ExportProcessSlideOver.vue`**
  - Painel lateral (Modal Slide-over) para Novo/Edição de contrato.
  - Contém a instância do `useForm` do Inertia e toda a lógica de persistência de dados.
  - O form é divido em 3 abas lógicas:
    1. **Geral & Produto**: Data, Contrato, Exporter, Importer, Product, Registro.
    2. **Financeiro**: Qtd (Ton), Preço/Ton, Valores Anuais, Comissão, Câmbio, e Datas/Valores a Pagar e Pagos.
    3. **Logística & Status**: Incidente, Vídeo, Status Atual, ETA, ETD, DHL e Observações.

## 5. Dicionário de Status e Cores
As regras de cores dos status são controladas no front-end para facilitar a leitura visual:
- **Verde (`emerald`)**: Finalizado ou Invoice Enviada.
- **Vermelho (`rose`)**: Atrasos ou faltas.
- **Azul (`blue`)**: Transbordos.
- **Amarelo (`amber`)**: A embarcar.
- **Cinza (`gray`)**: Pendente / Outros.

## 6. Padronização Visual
As cores adotadas neste módulo seguem estritamente as tonalidades de **Azul (`blue-500` ao `blue-900`)** definidos na paleta visual da interface original, evitando cores default como `indigo`.

---
*Documentação gerada automaticamente para referência de contexto (Memória) da IA ou de Desenvolvedores em manutenções futuras.*
