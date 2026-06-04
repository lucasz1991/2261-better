<?php

namespace App\Console\Commands;

use App\Jobs\DispatchDueSyntheticClaimRatings;
use Illuminate\Console\Command;

class RunDueSyntheticClaimRatingsCommand extends Command
{
    protected $signature = 'ratings:run-due-synthetic {--limit=25 : Maximale Anzahl faelliger Datensaetze}';

    protected $description = 'Startet faellige interne synthetische Bewertungsdatensaetze.';

    public function handle(): int
    {
        $report = (new DispatchDueSyntheticClaimRatings((int) $this->option('limit')))->handle();

        if (! is_array($report)) {
            $this->error('Ausfuehrungsjob hat keinen Report zurueckgegeben.');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('Ausfuehrungsreport');
        $this->table(['Feld', 'Wert'], [
            ['Generierung aktiv', $this->yesNo((bool) data_get($report, 'form_filling.enabled'))],
            ['Modus', (string) data_get($report, 'form_filling.mode', '-')],
            ['Limit', (string) ($report['limit'] ?? 0)],
            ['Faellige Bewertungen', (string) ($report['due_count'] ?? 0)],
            ['Verarbeitet', (string) ($report['dispatched_count'] ?? 0)],
            ['Vorbereitete ausgefuehrt', (string) ($report['executed_count'] ?? 0)],
            ['Rueckruf-Sperre uebersprungen', (string) ($report['skipped_manual_only_count'] ?? 0)],
            ['Fehler', (string) ($report['failed_count'] ?? 0)],
            ['Ergebnis', (string) ($report['reason'] ?? '-')],
        ]);

        if (! empty($report['dispatched'])) {
            $this->info('Verarbeitete Bewertungen');
            $this->table(
                ['ID', 'Base-ID', 'Lokaler User', 'Base-User', 'Aktion', 'Geplant fuer', 'Typ', 'Untertyp', 'Versicherung', 'Fehler'],
                collect($report['dispatched'])->map(fn (array $item): array => [
                    '#' . ($item['id'] ?? '-'),
                    isset($item['base_claim_rating_id']) && $item['base_claim_rating_id'] ? '#' . $item['base_claim_rating_id'] : '-',
                    isset($item['synthetic_rating_user_id']) && $item['synthetic_rating_user_id'] ? '#' . $item['synthetic_rating_user_id'] : '-',
                    isset($item['base_user_id']) && $item['base_user_id'] ? '#' . $item['base_user_id'] : '-',
                    match ($item['action'] ?? '') {
                        'prepared_marked_executed' => 'vorbereitete Bewertung ausgefuehrt',
                        'generated_and_executed' => 'AI generiert und ausgefuehrt',
                        'generation_failed' => 'AI-Generierung fehlgeschlagen',
                        default => $item['action'] ?? '-',
                    },
                    $item['scheduled_for'] ?? '-',
                    $item['type_id'] ?? '-',
                    $item['subtype_id'] ?? '-',
                    $item['insurance_id'] ?? '-',
                    $item['error'] ?? '-',
                ])->all()
            );
        }

        if (! empty($report['skipped'])) {
            $this->warn('Uebersprungene Bewertungen');
            $this->table(
                ['ID', 'Aktion', 'Geplant fuer', 'Typ', 'Untertyp', 'Versicherung'],
                collect($report['skipped'])->map(fn (array $item): array => [
                    '#' . ($item['id'] ?? '-'),
                    match ($item['action'] ?? '') {
                        'manual_only_after_retract' => 'nach Rueckruf nur manuell',
                        default => $item['action'] ?? '-',
                    },
                    $item['scheduled_for'] ?? '-',
                    $item['type_id'] ?? '-',
                    $item['subtype_id'] ?? '-',
                    $item['insurance_id'] ?? '-',
                ])->all()
            );
        }

        if (! ($report['ok'] ?? false)) {
            $this->warn((string) ($report['reason'] ?? 'Faellige Bewertungen wurden nicht gestartet.'));
        }

        return self::SUCCESS;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'ja' : 'nein';
    }
}
