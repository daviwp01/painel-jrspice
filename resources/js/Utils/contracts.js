/**
 * Shared formatting helpers for the contracts module.
 *
 * These used to be duplicated (with divergent behaviour) across five Vue files.
 */

/**
 * @param {string|Date|null|undefined} value
 * @param {{ month?: string, day?: string }} [options] pass nothing for the short date
 * @returns {string}
 */
export const formatDate = (value, options = {}) => {
  if (!value) return '—';

  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
  if (Number.isNaN(date.getTime())) return String(value);

  return options.withTime
    ? date.toLocaleDateString('pt-BR')
    : date.toLocaleDateString('pt-BR', options);
};

/**
 * @param {string|Date|null|undefined} value
 * @returns {string} YYYY-MM-DD, safe for <input type="date">
 */
export const toDateInput = (value) => (value ? String(value).slice(0, 10) : '');

/**
 * Adds or subtracts calendar days without touching the UTC boundary.
 *
 * The previous implementation used toISOString(), which shifted the day
 * backwards for anything west of UTC (America/Sao_Paulo).
 *
 * @param {string|null|undefined} value base date (YYYY-MM-DD); defaults to today
 * @param {number} days
 * @returns {string} YYYY-MM-DD
 */
export const addDays = (value, days) => {
  const base = value ? new Date(`${toDateInput(value)}T00:00:00`) : new Date();
  if (Number.isNaN(base.getTime())) return '';

  base.setDate(base.getDate() + days);

  const month = String(base.getMonth() + 1).padStart(2, '0');
  const day = String(base.getDate()).padStart(2, '0');

  return `${base.getFullYear()}-${month}-${day}`;
};


/**
 * Progress of a shipment between its own ETD and ETA. Never invents a value:
 * returns 0 when the contract has no dates and 100 once it is finished.
 *
 * @param {{ etd_date?: string, eta_date?: string, status?: string }} contract
 * @returns {number} 0..100
 */
export const getShipmentProgress = ({ etd_date: etd, eta_date: eta, status } = {}) => {
  if (String(status || '').toLowerCase().includes('finalizado')) return 100;
  if (!etd || !eta) return 0;

  const start = new Date(`${toDateInput(etd)}T00:00:00`);
  const end = new Date(`${toDateInput(eta)}T00:00:00`);
  const today = new Date();

  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) return 0;
  if (today <= start) return 0;
  if (today >= end) return 100;

  return Math.min(98, Math.max(0, Math.round(((today - start) / (end - start)) * 100)));
};

/**
 * @param {{ eta_date?: string, status?: string }} contract
 * @returns {number|null} null when there is nothing to count down to
 */
export const getRemainingDays = ({ eta_date: eta, status } = {}) => {
  if (!eta || String(status || '').toLowerCase().includes('finalizado')) return null;

  const target = new Date(`${toDateInput(eta)}T00:00:00`);
  if (Number.isNaN(target.getTime())) return null;

  const diff = target - new Date();

  return diff < 0 ? 0 : Math.ceil(diff / 86400000);
};

/**
 * @param {number|string|null|undefined} value
 * @param {{ symbol?: string, digits?: number }} [options]
 * @returns {string}
 */
export const formatCurrency = (value, { symbol = 'US$', digits = 2 } = {}) => {
  if (value === null || value === undefined || value === '') return '—';

  return `${symbol} ${Number(value).toLocaleString('pt-BR', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  })}`;
};

const STATUS_STYLES = [
  { match: ['finalizado'], class: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
  { match: ['invoice'], class: 'bg-sky-50 text-sky-700 border-sky-200', dot: 'bg-sky-500' },
  { match: ['faltando', 'atraso'], class: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' },
  { match: ['transbordo', 'chegou'], class: 'bg-violet-50 text-violet-700 border-violet-200', dot: 'bg-violet-500' },
  { match: ['embarcar', 'embarque'], class: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
];

/**
 * @param {string|null|undefined} status
 * @returns {{ class: string, dot: string }}
 */
export const getStatusConfig = (status) => {
  if (!status) return { class: 'bg-slate-50 text-slate-600 border-slate-200', dot: 'bg-slate-400' };

  const value = String(status).toLowerCase();
  const hit = STATUS_STYLES.find((entry) => entry.match.some((needle) => value.includes(needle)));

  return hit ?? { class: 'bg-slate-50 text-slate-600 border-slate-200', dot: 'bg-slate-400' };
};

/**
 * Presentational identity of the nine supported carriers.
 * Keyed by the `carrier_code` returned by the tracking API.
 *
 * @type {Record<string, { color: string, logo: string }>}
 */
export const CARRIERS = {
  hmm: { color: 'border-red-500 text-red-600 bg-red-50', logo: 'HMM' },
  evergreen: { color: 'border-emerald-600 text-emerald-700 bg-emerald-50', logo: 'EVERGREEN' },
  maersk: { color: 'border-sky-500 text-sky-600 bg-sky-50', logo: 'MAERSK' },
  cma_cgm: { color: 'border-blue-700 text-blue-800 bg-blue-50', logo: 'CMA CGM' },
  hapag_lloyd: { color: 'border-orange-500 text-orange-600 bg-orange-50', logo: 'HAPAG-LLOYD' },
  msc: { color: 'border-yellow-600 text-yellow-700 bg-yellow-50', logo: 'MSC' },
  oocl: { color: 'border-rose-600 text-rose-700 bg-rose-50', logo: 'OOCL' },
  cosco: { color: 'border-teal-700 text-teal-800 bg-teal-50', logo: 'COSCO' },
  zim: { color: 'border-slate-800 text-slate-900 bg-slate-100', logo: 'ZIM' },
};

/**
 * Matches either the API carrier_code (maersk, cma_cgm…) or the free-text name
 * stored on the contract (Maersk, CMA CGM, Hapag lloyd…).
 *
 * @param {string|null|undefined} carrier
 * @returns {{ color: string, logo: string }}
 */
const CARRIER_ALIASES = {
  maersk: 'maersk',
  maerskline: 'maersk',
  msc: 'msc',
  mediterraneanshipping: 'msc',
  cmacgm: 'cma_cgm',
  cmacgmgroup: 'cma_cgm',
  hapaglloyd: 'hapag_lloyd',
  hapag: 'hapag_lloyd',
  evergreen: 'evergreen',
  evergreenmarine: 'evergreen',
  hmm: 'hmm',
  hyundai: 'hmm',
  hyundaimerchantmarine: 'hmm',
  oocl: 'oocl',
  cosco: 'cosco',
  coscoshipping: 'cosco',
  zim: 'zim',
  zimintegratedshipping: 'zim',
};

export const getCarrierConfig = (carrier) => {
  if (!carrier) return { color: 'border-slate-300 text-slate-600 bg-slate-50', logo: '—' };

  const normalized = String(carrier).toLowerCase().replace(/[^a-z0-9]/g, '');
  const code = CARRIER_ALIASES[normalized] || normalized.replace(/_/g, '');

  return CARRIERS[code] ?? {
    color: 'border-slate-300 text-slate-600 bg-slate-50',
    logo: '—',
  };
};

/**
 * Human readable label for the normalized tracking statuses.
 *
 * @param {string|null|undefined} status
 * @returns {string}
 */
export const getTrackingStatusLabel = (status) =>
  ({
    IN_TRANSIT: 'Em trânsito',
    DISCHARGED: 'Descarregado no porto',
    DELIVERED: 'Entregue',
    PENDING: 'Aguardando embarque',
    UNKNOWN: 'Situação indisponível',
  })[String(status || '').toUpperCase()] ?? 'Situação indisponível';
