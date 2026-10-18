<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for contract attachments, shared by the back-end
 * validation and the front-end pre-check.
 */
final class ContractDocumentRules
{
    /** 5 GB — the ceiling for videos and every other document. */
    public const MAX_BYTES = 5 * 1024 * 1024 * 1024;

    /** Laravel validates upload size in kilobytes. */
    public const MAX_KILobytes = 5 * 1024 * 1024;

    /**
     * Formats accepted on desktop and mobile without conversion.
     */
    public const EXTENSIONS = [
        // vídeo
        'mp4', 'mov', 'm4v', 'webm', 'avi',
        // documento
        'pdf', 'doc', 'docx',
        // planilha
        'xls', 'xlsx',
        // imagem
        'jpg', 'jpeg', 'png',
    ];

    public static function mimeTypes(): string
    {
        return implode(',', self::EXTENSIONS);
    }

    public static function humanMaxSize(): string
    {
        return '5 GB';
    }

    public static function typeFor(string $extension): string
    {
        return match (strtolower($extension)) {
            'mp4', 'mov', 'm4v', 'webm', 'avi' => 'video',
            'jpg', 'jpeg', 'png' => 'image',
            'xls', 'xlsx' => 'spreadsheet',
            default => 'document',
        };
    }
}
