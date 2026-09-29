const CLASS_NAME = 'xpice-fixed-tooltip';

const escapeHtml = (value) =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

const POSITION_STYLES = {
    center: { top: '12px', left: '50%', right: 'auto', transform: 'translateX(-50%)' },
    left: { top: '12px', left: '12px', right: 'auto', transform: 'none' },
    right: { top: '12px', left: 'auto', right: '12px', transform: 'none' },
};

function getTooltipElement(parent, align) {
    let el = parent.querySelector(`.${CLASS_NAME}`);

    if (el) return el;

    el = document.createElement('div');
    el.className = CLASS_NAME;
    el.setAttribute('aria-hidden', 'true');

    Object.assign(el.style, {
        position: 'absolute',
        padding: '10px 14px',
        background: '#0f172a',
        color: '#f8fafc',
        borderRadius: '14px',
        boxShadow: '0 12px 32px rgba(15, 23, 42, 0.35)',
        fontSize: '12px',
        fontWeight: '700',
        lineHeight: '1.55',
        letterSpacing: '0.02em',
        pointerEvents: 'none',
        zIndex: '20',
        maxWidth: 'calc(100% - 24px)',
        opacity: '0',
        transition: 'opacity 120ms ease',
        willChange: 'opacity',
        ...POSITION_STYLES[align] || POSITION_STYLES.center,
    });

    parent.appendChild(el);
    return el;
}

/**
 * Tooltip do Chart.js FIXO no topo do gráfico.
 * Não acompanha o mouse: só atualiza o conteúdo enquanto o cursor
 * está sobre o gráfico e some quando sai.
 *
 * @param {object} [options]
 * @param {'center'|'left'|'right'} [options.align='center'] - posição horizontal fixa
 * @returns {function} handler para plugins.tooltip.external
 *
 * IMPORTANTE: usar junto com `tooltip.enabled = false` (senão o tooltip
 * padrão do Chart.js continua sendo desenhado junto com este).
 */
export function createFixedTooltip({ align = 'center' } = {}) {
    return (context) => {
        const { chart, tooltip } = context;
        const parent = chart?.canvas?.parentNode;
        if (!parent) return;

        const el = getTooltipElement(parent, align);

        // Esconde quando não há ponto ativo (saiu do gráfico) ou quando o
        // Chart.js marca o tooltip como invisível.
        const active = typeof tooltip?.getActiveElements === 'function'
            ? tooltip.getActiveElements()
            : [];
        if (!tooltip || !active.length || tooltip.opacity === 0) {
            el.style.opacity = '0';
            return;
        }

        const title = (tooltip.title || []).filter(Boolean).join(' · ');

        // `body` traz o texto já formatado pelos callbacks.label do gráfico
        // (ex.: "2026: $ 6.350,00"). Só os anos com preço no período são
        // exibidos — anos sem dado não são listados.
        const dataPoints = tooltip.dataPoints || [];
        const rows = (tooltip.body || [])
            .map((item, index) => {
                const text = (item.lines || []).join(' ').trim();
                if (!text) return '';
                const color =
                    dataPoints[index]?.dataset?.borderColor ||
                    dataPoints[index]?.dataset?.backgroundColor ||
                    '#94a3b8';
                return `
                    <div style="display:flex;align-items:center;gap:8px;white-space:nowrap;">
                        <span style="width:12px;height:12px;border-radius:9999px;background:${escapeHtml(color)};box-sizing:border-box;border:2px solid #ffffff;flex-shrink:0;"></span>
                        <span>${escapeHtml(text)}</span>
                    </div>`;
            })
            .filter(Boolean)
            .join('');

        if (!rows) {
            el.style.opacity = '0';
            return;
        }

        el.innerHTML = `
            ${
                title
                    ? `<div style="font-size:10px;font-weight:800;letter-spacing:0.14em;text-transform:uppercase;color:#94a3b8;margin-bottom:6px;">${escapeHtml(title)}</div>`
                    : ''
            }
            ${rows}`;

        el.style.opacity = '1';
    };
}
