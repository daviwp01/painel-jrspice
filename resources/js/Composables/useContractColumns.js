import { computed, ref, watch } from 'vue';

/**
 * Column model of the contracts spreadsheet.
 *
 * `fixed` columns are the contract identity: they are always visible, always
 * first and cannot be dragged or hidden. Everything else the user owns.
 */
export const CONTRACT_COLUMNS = [
    { key: 'date', label: 'Data', fixed: true, recommended: true },
    { key: 'contract_number', label: 'Contrato', fixed: true, recommended: true },
    { key: 'register_number', label: 'Registro', recommended: false },
    { key: 'exporter', label: 'Exportador', recommended: true },
    { key: 'importer', label: 'Importador', recommended: true },
    { key: 'country', label: 'País', recommended: true },
    { key: 'product', label: 'Produto', recommended: true },
    { key: 'quantity_tons', label: 'Quantidade', recommended: true, numeric: true },
    { key: 'price_per_ton_usd', label: 'Preço Ton US$', recommended: true, numeric: true },
    { key: 'sales_usd', label: 'Venda US$', recommended: true, numeric: true },
    { key: 'cumulative_sales_usd', label: 'Venda Anual US$', recommended: false, numeric: true },
    { key: 'commission_usd', label: 'Comissão US$', recommended: true, numeric: true },
    { key: 'cumulative_commission_usd', label: 'TT Comissão US$', recommended: false, numeric: true },
    { key: 'exchange_rate', label: 'Taxa de Câmbio', recommended: false, numeric: true },
    { key: 'estimated_euro', label: 'Estimado em Euro', recommended: false, numeric: true },
    { key: 'estimated_receipt_date', label: 'Data Estimada Recebimento', recommended: false },
    { key: 'seller', label: 'Vendedor', recommended: true },
    { key: 'to_pay_usd', label: 'A pagar', recommended: false, numeric: true },
    { key: 'receipt_date', label: 'Recebimento', recommended: false },
    { key: 'paid_in_date', label: 'Pago em', recommended: false },
    { key: 'paid_in_brl', label: 'Pago em Real', recommended: false, numeric: true },
    { key: 'incident', label: 'Incidente', recommended: false },
    { key: 'video_sent', label: 'Video enviado', recommended: false },
    { key: 'video_date', label: 'DATA VÍDEO', recommended: false },
    { key: 'status', label: 'STATUS', recommended: true },
    { key: 'dhl_date', label: 'Data DHL', recommended: false },
    { key: 'etd_date', label: 'Data embarque ETD', recommended: false },
    { key: 'eta_date', label: 'Data da chegada ETA', recommended: true },
    { key: 'dhl_number', label: 'DHL Nº', recommended: false },
    { key: 'observations', label: 'Observações', recommended: false },
];

const COLUMN_BY_KEY = new Map(CONTRACT_COLUMNS.map((column) => [column.key, column]));

const DEFAULT_ORDER = CONTRACT_COLUMNS.map((column) => column.key);
const DEFAULT_VISIBLE = CONTRACT_COLUMNS.filter((column) => column.recommended).map((column) => column.key);

const STORAGE_PREFIX = 'jrspice_contract_columns';

/** Bump when the stored shape changes; readers keep accepting older shapes. */
const STORAGE_VERSION = 2;

const instances = new Map();

/** Lookup used by cell helpers; never returns a missing column. */
export function contractColumn(key) {
    return COLUMN_BY_KEY.get(key);
}

export function contractColumnsStorageKey(userId) {
    return `${STORAGE_PREFIX}_${userId || 'guest'}`;
}

/**
 * Shared column state for one user. The table and the "Colunas" panel read the
 * same computeds, so reordering the panel reorders the table in place.
 */
export function useContractColumns(storageKey) {
    if (instances.has(storageKey)) {
        return instances.get(storageKey);
    }

    const stored = readStorage(storageKey);

    // `order` only holds the columns the user may move. Identity columns are
    // re-attached in front of them by `orderedColumns`.
    const movableKeys = ref(stored.order);
    const visibleKeys = ref(new Set(stored.visible));

    const orderedColumns = computed(() => {
        const movable = movableKeys.value
            .map((key) => COLUMN_BY_KEY.get(key))
            .filter(Boolean);

        return [
            ...CONTRACT_COLUMNS.filter((column) => column.fixed),
            ...movable,
        ];
    });

    const visibleColumns = computed(() =>
        orderedColumns.value.filter((column) => visibleKeys.value.has(column.key)),
    );

    const toggleableColumns = computed(() => orderedColumns.value.filter((column) => !column.fixed));

    const fixedColumns = computed(() => orderedColumns.value.filter((column) => column.fixed));

    const hiddenCount = computed(() => toggleableColumns.value.filter((column) => !visibleKeys.value.has(column.key)).length);

    const isVisible = (key) => visibleKeys.value.has(key);

    const isCustomized = computed(() => {
        const sameOrder = movableKeys.value.length === CONTRACT_COLUMNS.filter((c) => !c.fixed).length
            && movableKeys.value.every((key, index) => key === CONTRACT_COLUMNS.filter((c) => !c.fixed)[index].key);
        const sameVisible = toggleableColumns.value.every((column) => visibleKeys.value.has(column.key))
            && hiddenCount.value === 0;

        return !sameOrder || !sameVisible;
    });

    const persist = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify({
                v: STORAGE_VERSION,
                order: [...movableKeys.value],
                visible: CONTRACT_COLUMNS
                    .filter((column) => visibleKeys.value.has(column.key))
                    .map((column) => column.key),
            }));
        } catch {
            /* modo privado ou cota cheia: a preferência vale só para a aba */
        }
    };

    const toggle = (key) => {
        const column = COLUMN_BY_KEY.get(key);
        if (!column || column.fixed) return;

        const next = new Set(visibleKeys.value);
        next.has(key) ? next.delete(key) : next.add(key);
        visibleKeys.value = next;
        persist();
    };

    const move = (key, direction) => {
        const index = movableKeys.value.indexOf(key);
        if (index < 0) return;

        const target = index + direction;
        if (target < 0 || target >= movableKeys.value.length) return;

        const next = [...movableKeys.value];
        [next[index], next[target]] = [next[target], next[index]];
        movableKeys.value = next;
        persist();
    };

    const reorder = (fromKey, toKey) => {
        if (fromKey === toKey) return;

        const next = [...movableKeys.value];
        const from = next.indexOf(fromKey);
        const to = next.indexOf(toKey);
        if (from < 0 || to < 0) return;

        next.splice(from, 1);
        next.splice(to, 0, fromKey);
        movableKeys.value = next;
        persist();
    };

    const reset = () => {
        movableKeys.value = CONTRACT_COLUMNS.filter((column) => !column.fixed).map((column) => column.key);
        visibleKeys.value = new Set(DEFAULT_VISIBLE);
        persist();
    };

    const api = {
        fixedColumns,
        orderedColumns,
        visibleColumns,
        toggleableColumns,
        hiddenCount,
        isCustomized,
        isVisible,
        toggle,
        move,
        reorder,
        reset,
    };

    instances.set(storageKey, api);

    return api;
}

/**
 * Reads any historical shape of the preference:
 *
 * - v1 was a bare array of visible keys, so old installs keep working and are
 *   upgraded on the next write;
 * - unknown keys (a column removed from the product) are dropped;
 * - columns added after the preference was saved are appended at the end, so a
 *   deploy never makes a column unreachable.
 */
function readStorage(storageKey) {
    const fallback = {
        order: CONTRACT_COLUMNS.filter((column) => !column.fixed).map((column) => column.key),
        visible: [...DEFAULT_VISIBLE],
    };

    let raw;
    try {
        raw = localStorage.getItem(storageKey);
    } catch {
        return fallback;
    }

    if (!raw) return fallback;

    let parsed;
    try {
        parsed = JSON.parse(raw);
    } catch {
        return fallback;
    }

    const storedOrder = Array.isArray(parsed) ? null : parsed?.order;
    const storedVisible = Array.isArray(parsed) ? parsed : parsed?.visible;

    const movableKeys = CONTRACT_COLUMNS.filter((column) => !column.fixed).map((column) => column.key);

    const order = (Array.isArray(storedOrder) ? storedOrder : [])
        .filter((key) => movableKeys.includes(key));

    for (const key of movableKeys) {
        if (!order.includes(key)) order.push(key);
    }

    const visible = Array.isArray(storedVisible)
        ? storedVisible.filter((key) => COLUMN_BY_KEY.has(key))
        : [...DEFAULT_VISIBLE];

    return { order, visible: visible.length ? visible : [...DEFAULT_VISIBLE] };
}
