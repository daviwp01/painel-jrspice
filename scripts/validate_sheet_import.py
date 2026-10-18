"""Compara a aba "Painel de info Vendas" com o banco, campo a campo.

Uso: python3 storage/framework/validate_sheet.py
Regras de data espelham App\\Services\\Contracts\\Spreadsheet\\ValueNormalizer.
"""
import collections
import datetime
import re
import subprocess
import sys

import openpyxl

XLSX = 'contratos/Painel de dados ATUAL.xlsx'  # relativo à raiz do projeto
MIN_YEAR, MAX_YEAR = 1990, 2100
MIN_SERIAL, MAX_SERIAL = 20000, 60000
RESIDUE_COLS = (23, 24, 26)  # DATA VÍDEO, STATUS, Data DHL


def db_dump():
    sql = (
        "select c.source_row, c.dhl_date, c.etd_date, c.eta_date, "
        "c.dhl_number, c.video_reference, c.video_date, c.observations, "
        "ci.source_row, ci.etd_date, ci.eta_date, ci.dhl_number, "
        "ci.video_reference, ci.video_date, ci.observations "
        "from contracts c left join contract_items ci on ci.contract_id=c.id "
        "order by c.source_row, ci.position"
    )
    out = subprocess.run(
        ['docker', 'exec', 'jrspice-power-bi_db', 'mariadb', '-uuser', '-ppassword',
         'jrspice-power-bi', '-N', '-e', sql],
        capture_output=True, text=True,
    )
    if out.returncode:
        print(out.stderr, file=sys.stderr)
        sys.exit(1)
    return out.stdout


def load_db():
    contract, items = {}, {}
    for line in db_dump().splitlines():
        p = line.split('\t')
        if len(p) < 15:
            continue
        if p[0] not in ('NULL', ''):
            contract[int(p[0])] = dict(zip(
                ['dhl_date', 'etd_date', 'eta_date', 'dhl_number',
                 'video_reference', 'video_date', 'observations'], p[1:8]))
        if p[8] not in ('NULL', ''):
            items[int(p[8])] = dict(zip(
                ['etd_date', 'eta_date', 'dhl_number',
                 'video_reference', 'video_date', 'observations'], p[9:15]))
    return contract, items


def norm_text(value):
    if value is None:
        return None
    if isinstance(value, (int, float)) and not isinstance(value, bool) and float(value).is_integer():
        value = int(value)
    text = re.sub(r'\s+', ' ', str(value)).strip()
    return text or None


def to_date(value):
    """Janela de plausibilidade idêntica ao ValueNormalizer."""
    if isinstance(value, (datetime.datetime, datetime.date)):
        candidate = value.strftime('%Y-%m-%d')
    elif isinstance(value, (int, float)) and not isinstance(value, bool) and MIN_SERIAL <= float(value) <= MAX_SERIAL:
        candidate = (datetime.date(1899, 12, 30) + datetime.timedelta(days=int(value))).strftime('%Y-%m-%d')
    else:
        return None
    return candidate if MIN_YEAR <= int(candidate[:4]) <= MAX_YEAR else None


def to_tracking(value):
    return to_date(value) or norm_text(value)


def load_blocks():
    wb = openpyxl.load_workbook(XLSX, data_only=True, read_only=True)
    ws = wb['Painel de info Vendas']
    rows = list(ws.iter_rows(min_row=3, max_row=2865, max_col=31, values_only=True))

    blocks, current = [], None
    for offset, row in enumerate(rows):
        sheet_row = offset + 3
        if not any(v not in (None, '', '...') for v in row):
            continue
        if not any(row[i] not in (None, '', '...') for i in range(31) if i not in RESIDUE_COLS):
            continue

        number = norm_text(row[1])
        date = to_date(row[0])
        if number is None and date is None and row[6] is None and row[7] is None:
            continue

        if current is None or number is None or number != current['number'] or date != current['date']:
            if current:
                blocks.append(current)
            current = {'number': number, 'date': date, 'rows': []}
        current['rows'].append((sheet_row, row))

    if current:
        blocks.append(current)

    return blocks


def main():
    contract, items = load_db()
    blocks = load_blocks()

    problems = collections.Counter()
    examples = collections.defaultdict(list)
    not_imported = []
    checked = 0

    for block in blocks:
        first_row, _ = block['rows'][0]
        if first_row not in contract:
            not_imported.append(f"linha {first_row} contrato {block['number']}")
            continue

        header = contract[first_row]

        for sheet_row, row in block['rows']:
            item = items.get(sheet_row, {})

            def effective(field, source=None):
                source = item if source is None else source
                value = source.get(field)
                return value if value not in ('', 'NULL', None) else (header.get(field) or None)

            def clean(value):
                return None if value in ('', 'NULL', None) else value

            video_date_from_ref = to_date(row[22])
            video_reference = None if video_date_from_ref else norm_text(row[22])

            # "Data Status" (col. Z) foi promovido a "Data embarque ETD": ele
            # tem prioridade sobre a coluna AB original.
            expected_etd = to_date(row[25]) or to_date(row[27])

            checks = [
                ('dhl_date', to_date(row[26]), effective('dhl_date')),
                ('etd_date', expected_etd, effective('etd_date')),
                ('eta_date', to_date(row[28]), effective('eta_date')),
                ('dhl_number', to_tracking(row[29]), effective('dhl_number')),
                ('observations', norm_text(row[30]), effective('observations')),
                ('video_date', to_date(row[23]) or video_date_from_ref, effective('video_date')),
                ('video_reference', video_reference, effective('video_reference')),
            ]

            for field, expected, actual in checks:
                checked += 1
                actual = clean(actual)
                if expected is None:
                    continue
                if actual is None:
                    problems[field] += 1
                    if len(examples[field]) < 5:
                        examples[field].append(
                            f"linha {sheet_row} contrato {block['number']}: esperado {expected}, sistema vazio")
                elif str(actual) != str(expected):
                    key = f'{field} (divergente)'
                    problems[key] += 1
                    if len(examples[key]) < 5:
                        examples[key].append(
                            f"linha {sheet_row} contrato {block['number']}: planilha={expected} sistema={actual}")

    print(f'verificações: {checked}')
    print(f'blocos na planilha: {len(blocks)} | contratos no sistema: {len(contract)} | itens: {len(items)}')
    print(f'blocos não importados: {not_imported}')

    if not problems:
        print('\nOK — nenhum dado da planilha perdido ou divergente.')
        return 0

    for key, count in problems.most_common():
        print(f'  {key}: {count}')
        for line in examples[key]:
            print(f'      {line}')
    return 1


if __name__ == '__main__':
    sys.exit(main())
