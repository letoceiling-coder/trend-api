<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\TaCoverageService;
use Illuminate\Console\Command;

class TaStatsCommand extends Command
{
    protected $signature = 'ta:stats';

    protected $description = 'Вывести количество спарсенных сущностей в БД (блоки по типам, квартиры, детали, справочники)';

    public function handle(TaCoverageService $coverage): int
    {
        $counts = $coverage->getParsedCounts();

        $this->newLine();
        $this->info('=== Спарсено в БД (TrendAgent) ===');
        $this->newLine();

        $this->table(
            ['Сущность', 'Таблица', 'Количество'],
            [
                ['Комплексы / ЖК / Посёлки / Дома (блоки)', 'ta_blocks', (string) $counts['blocks_total']],
                ['Квартиры', 'ta_apartments', (string) $counts['apartments_total']],
                ['Детали блоков (карточки комплексов)', 'ta_block_details', (string) $counts['block_details_total']],
                ['Детали квартир (карточки квартир)', 'ta_apartment_details', (string) $counts['apartment_details_total']],
                ['Справочники (по типам)', 'ta_directories', (string) $counts['directories_total']],
            ]
        );

        if (! empty($counts['blocks_by_kind'])) {
            $this->newLine();
            $this->line('Блоки по типу (поле kind):');
            foreach ($counts['blocks_by_kind'] as $kind => $cnt) {
                $this->line("  — {$kind}: {$cnt}");
            }
        }

        if (! empty($counts['directories_by_type'])) {
            $this->newLine();
            $this->line('Справочники по типу:');
            foreach ($counts['directories_by_type'] as $type => $cnt) {
                $this->line("  — {$type}: {$cnt}");
            }
        }

        $this->newLine();
        $this->comment('Примечание: Паркинги, коммерция, подрядчики, участки, проекты домов в текущем парсере не вынесены в отдельные таблицы — только блоки (ta_blocks) и квартиры (ta_apartments). Тип объекта в блоке хранится в поле kind.');
        $this->newLine();

        return self::SUCCESS;
    }
}
