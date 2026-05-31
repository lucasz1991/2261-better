<?php

namespace App\Console\Commands;

use App\Jobs\PlanSyntheticClaimRatings;
use Illuminate\Console\Command;

class PlanSyntheticClaimRatingsCommand extends Command
{
    protected $signature = 'ratings:plan-synthetic {date? : Datum im Format YYYY-MM-DD} {--count= : Anzahl fuer diesen Tag ueberschreiben}';

    protected $description = 'Plant interne synthetische Bewertungsdatensaetze anhand der Bewertungs-Einstellungen.';

    public function handle(): int
    {
        $count = $this->option('count');

        $report = (new PlanSyntheticClaimRatings(
            $this->argument('date'),
            $count !== null && $count !== '' ? (int) $count : null
        ))->handle();

        if (! is_array($report)) {
            $this->error('Planungsjob hat keinen Report zurueckgegeben.');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('Planungsreport');
        $this->table(['Feld', 'Wert'], [
            ['Generierung aktiv', $this->yesNo((bool) data_get($report, 'form_filling.enabled'))],
            ['Modus', (string) data_get($report, 'form_filling.mode', '-')],
            ['Zieltag', (string) ($report['target_date'] ?? '-')],
            ['Tagesziel', (string) ($report['target_count'] ?? 0)],
            ['Bereits geplant', (string) ($report['already_planned'] ?? 0)],
            ['Noch offen', (string) ($report['remaining'] ?? 0)],
            ['Gueltige Base-Kombinationen', (string) ($report['eligible_pairs'] ?? 0)],
            ['Gewichtungs-Fallback', $this->yesNo((bool) ($report['weight_fallback'] ?? false))],
            ['Neu geplant', (string) ($report['created_count'] ?? 0)],
            ['Uebersprungen', (string) ($report['skipped_count'] ?? 0)],
            ['Base-Verbindung', (string) ($report['connection'] ?? '-')],
            ['Ergebnis', (string) ($report['reason'] ?? '-')],
        ]);

        if ($report['weight_fallback'] ?? false) {
            $this->warn('Die gespeicherten Typ-/Untertyp-Gewichte passen nicht zu den IDs in der Base-Datenbank. Es wurde gleichmaessig aus den aktiven Base-Kombinationen geplant.');
        }

        if (! empty($report['created'])) {
            $this->info('Neu geplante Bewertungen');
            $this->table(
                ['ID', 'Geplant fuer', 'Typ', 'Untertyp', 'Versicherung', 'Fragebogen'],
                collect($report['created'])->map(fn (array $item): array => [
                    '#' . ($item['id'] ?? '-'),
                    $item['scheduled_for'] ?? '-',
                    ($item['type_id'] ?? '-') . ' - ' . ($item['type_name'] ?? '-'),
                    ($item['subtype_id'] ?? '-') . ' - ' . ($item['subtype_name'] ?? '-'),
                    ($item['insurance_id'] ?? '-') . ' - ' . ($item['insurance_name'] ?? '-'),
                    $item['questionnaire_version_id'] ?? '-',
                ])->all()
            );
        }

        if (! empty($report['skipped'])) {
            $this->warn('Uebersprungene Slots');
            $this->table(
                ['Slot', 'Typ', 'Untertyp', 'Grund'],
                collect($report['skipped'])->map(fn (array $item): array => [
                    $item['slot'] ?? '-',
                    $item['type_id'] ?? '-',
                    $item['subtype_id'] ?? '-',
                    $item['reason'] ?? '-',
                ])->all()
            );
        }

        if (! ($report['ok'] ?? false)) {
            $this->warn((string) ($report['reason'] ?? 'Planung wurde nicht ausgefuehrt.'));
        }

        return self::SUCCESS;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'ja' : 'nein';
    }
}
