<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\User;
use App\Support\ContractDocumentRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storage of contract attachments.
 *
 * Files live on the dedicated `contracts` disk (local today, S3 when
 * CONTRACTS_DISK=s3) and are always stored under private paths, streamed
 * through the download/preview endpoints that re-check authorization.
 */
final class ContractDocumentService
{
    public const DISK = 'contracts';

    public function upload(Contract $contract, UploadedFile $file, User $uploader): ContractDocument
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $path = $file->store(
            sprintf('contracts/%d', $contract->id),
            [
                'disk' => self::DISK,
                'name' => sprintf('%s.%s', Str::uuid()->toString(), $extension),
            ],
        );

        if ($path === false) {
            throw new \RuntimeException('Não foi possível gravar o arquivo no disco de armazenamento.');
        }

        return ContractDocument::create([
            'contract_id' => $contract->id,
            'name' => $this->originalName($file),
            'file_path' => $path,
            'file_type' => ContractDocumentRules::typeFor($extension),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * Removes everything the attachment owns: the binary on disk, the database
     * row and — when the disk has real directories — the now empty contract
     * folder. Nothing is left behind to be found by a later upload.
     */
    public function delete(ContractDocument $document): void
    {
        $disk = Storage::disk(self::DISK);

        if ($this->isInsideDisk($document->file_path) && $disk->exists($document->file_path)) {
            $disk->delete($document->file_path);
        }

        $document->delete();

        $this->pruneEmptyDirectories($disk, dirname($document->file_path));
    }

    /**
     * Walks up from the file's folder removing every empty directory left
     * behind, so a contract that loses its last attachment leaves no trace.
     * S3 has no real directories, so there is nothing to prune there.
     *
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     */
    private function pruneEmptyDirectories($disk, string $directory): void
    {
        if (config('filesystems.disks.'.self::DISK.'.driver') !== 'local') {
            return;
        }

        while ($this->isInsideDisk($directory) && $directory !== '.') {
            // Só poda quando não sobrou nem arquivo nem subpasta: apagar a
            // pasta pai com outros contratos dentro destruiria os arquivos.
            if ($disk->files($directory) !== [] || $disk->directories($directory) !== []) {
                return;
            }

            $disk->deleteDirectory($directory);

            $parent = dirname($directory);
            if ($parent === '.' || $parent === $directory) {
                return;
            }

            $directory = $parent;
        }
    }

    /**
     * A stored path must never walk outside the disk root.
     */
    private function isInsideDisk(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return ! str_starts_with($path, '/') && ! str_contains($path, '..');
    }

    /**
     * Keeps the human readable name but strips anything that could escape the
     * storage directory.
     */
    private function originalName(UploadedFile $file): string
    {
        $name = basename((string) $file->getClientOriginalName());

        return Str::limit($name, 200, '...');
    }
}
