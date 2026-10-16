<?php

namespace App\Support;

final class ContractStatus
{
    public const TO_BOARD = 'A embarcar dia';

    public const TRANSSSHIPMENT = 'Transbordo até';

    public const ARRIVED_PORT = 'Chegou porto dia';

    public const PENDING_COMMISSION = 'Só faltando comissão';

    public const INVOICE_SENT = 'Invoice ENVIADA';

    public const FINISHED = 'Processo FINALIZADO';

    public const ALL = [
        self::TO_BOARD,
        self::TRANSSSHIPMENT,
        self::ARRIVED_PORT,
        self::PENDING_COMMISSION,
        self::INVOICE_SENT,
        self::FINISHED,
    ];

    public static function options(): array
    {
        return array_map(
            static fn (string $status): array => ['id' => $status, 'name' => $status],
            self::ALL
        );
    }

    public static function isValid(?string $status): bool
    {
        return $status === null || in_array($status, self::ALL, true);
    }

    public static function isFinished(?string $status): bool
    {
        return $status === self::FINISHED;
    }

    /**
     * Visual token used by the front-end badges.
     *
     * @return array{class: string, dot: string}
     */
    public static function color(?string $status): array
    {
        if ($status === null) {
            return ['class' => 'bg-slate-50/50 text-slate-600 border-slate-200/60', 'dot' => 'bg-slate-400'];
        }

        return match ($status) {
            self::FINISHED => ['class' => 'bg-emerald-50/35 text-emerald-800 border-emerald-500/15', 'dot' => 'bg-emerald-500'],
            self::INVOICE_SENT => ['class' => 'bg-sky-50/35 text-sky-800 border-sky-500/15', 'dot' => 'bg-sky-500'],
            self::PENDING_COMMISSION => ['class' => 'bg-rose-50/35 text-rose-800 border-rose-500/15', 'dot' => 'bg-rose-500'],
            self::TRANSSSHIPMENT, self::ARRIVED_PORT => ['class' => 'bg-purple-50/35 text-purple-800 border-purple-500/15', 'dot' => 'bg-purple-500'],
            self::TO_BOARD => ['class' => 'bg-amber-50/35 text-amber-800 border-amber-500/15', 'dot' => 'bg-amber-500'],
            default => ['class' => 'bg-slate-50/50 text-slate-600 border-slate-200/60', 'dot' => 'bg-slate-400'],
        };
    }
}
