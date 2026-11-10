<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\ProductTranslationGlossary;
use Illuminate\Console\Command;

/**
 * Seeds name_en / name_es for every product that still lacks them.
 *
 * Existing translations are kept unless --force is given. Matching,
 * search, sorting and price calculations always use the canonical
 * `name` column and are not touched by this command.
 */
class TranslateProducts extends Command
{
    protected $signature = 'products:translate
        {--force : Overwrite translations that are already filled}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Preenche name_en/name_es dos produtos existentes usando o glossário (exibição apenas)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $query = Product::query()->orderBy('id');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('name_en')->orWhereNull('name_es');
            });
        }

        $total = (clone $query)->count();
        $updated = 0;
        $unchanged = 0;
        $pending = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($query->cursor() as $product) {
            $translation = ProductTranslationGlossary::translate((string) $product->name);

            $payload = [];
            if ($force || filled($product->name_en) === false) {
                $payload['name_en'] = $translation['en'];
            }
            if ($force || filled($product->name_es) === false) {
                $payload['name_es'] = $translation['es'];
            }

            if ($payload === []) {
                $unchanged++;
                $bar->advance();

                continue;
            }

            $willBeSameAsName = ($payload['name_en'] ?? null) === $product->name
                && ($payload['name_es'] ?? null) === $product->name;

            if (! $dryRun) {
                $product->fill($payload)->saveQuietly();
            }

            if ($willBeSameAsName) {
                $pending[] = (string) $product->name;
            }

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $verb = $dryRun ? 'seriam atualizados' : 'atualizados';
        $this->info(sprintf('%d produto(s) %s.', $updated, $verb));

        if ($pending !== []) {
            $this->newLine();
            $this->comment(sprintf(
                '%d produto(s) sem termos no glossário (tradução igual ao nome original) — refine pela tela de Produtos:',
                count($pending)
            ));
            foreach (array_slice($pending, 0, 30) as $name) {
                $this->line('  - '.$name);
            }
            if (count($pending) > 30) {
                $this->line(sprintf('  ... e mais %d.', count($pending) - 30));
            }
        }

        return self::SUCCESS;
    }
}
