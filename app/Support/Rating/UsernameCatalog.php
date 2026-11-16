<?php

namespace App\Support\Rating;

/**
 * A local, versioned vocabulary. Each entry represents one word base, never
 * another spelling, separator, suffix or reordered version of the same base.
 * Adjectives describe people; the grammatical gender belongs to the noun.
 */
final class UsernameCatalog
{
    private const VERSION = '2026-11-16.1';

    private const MODIFIERS = [
        'achtsam', 'aktiv', 'aufgeschlossen', 'aufmerksam', 'ausdauernd',
        'ausgeglichen', 'bedacht', 'begeistert', 'beharrlich', 'behutsam',
        'belastbar', 'bescheiden', 'besonnen', 'bestaendig', 'bewegt',
        'charmant', 'dankbar', 'dynamisch', 'ehrlich', 'eifrig',
        'einfaellsreich', 'empfindsam', 'engagiert', 'entschlossen', 'entspannt',
        'erfahren', 'erfinderisch', 'ernsthaft', 'fantasievoll', 'feinfuehlig',
        'findig', 'fleissig', 'flexibel', 'flink', 'fokussiert',
        'frei', 'freimuetig', 'freundlich', 'friedlich', 'froehlich',
        'froh', 'furchtlos', 'geduldig', 'geerdet', 'gelassen',
        'gemuetlich', 'genau', 'genuegsam', 'geschickt',
        'gesellig', 'gestaltungsfreudig', 'gewissenhaft', 'gewitzt', 'gluecklich',
        'grossherzig', 'gruendlich', 'gutgelaunt', 'gutherzig', 'heiter',
        'hellwach', 'herzlich', 'hilfsbereit', 'hoffnungsvoll', 'humorvoll',
        'ideenreich', 'idealistisch', 'impulsiv', 'initiativ', 'interessiert',
        'intuitiv', 'kameradschaftlich', 'kenntnisreich', 'klar', 'klug',
        'konsequent', 'konzentriert', 'kreativ', 'lebendig', 'lebensfroh',
        'leidenschaftlich', 'leise', 'lernbegierig', 'lernfreudig', 'locker',
        'loesungsorientiert', 'loyal', 'lustig', 'massvoll', 'mitfuehlend',
        'motiviert', 'munter', 'mutig', 'nachdenklich', 'nachsichtig',
        'naturverbunden', 'neugierig', 'offen', 'optimistisch', 'ordentlich',
        'organisiert', 'originell', 'praktisch', 'praezise', 'pragmatisch',
        'quirlig', 'reflektiert', 'respektvoll', 'ruecksichtsvoll', 'ruhig',
        'sanft', 'schlagfertig', 'schnell', 'selbstbewusst', 'sorgfaeltig',
        'sorgsam', 'souveraen', 'spontan', 'sportlich', 'standhaft',
        'staunend', 'still', 'strukturiert', 'sympathisch', 'taktvoll',
        'tatkraeftig', 'tiefgruendig', 'tolerant', 'treu', 'umsichtig',
        'unaufgeregt', 'unbeschwert', 'unerschrocken', 'unternehmungslustig', 'verbindlich',
        'verlaesslich', 'verstaendnisvoll', 'vielseitig', 'vorausschauend', 'wach',
        'warmherzig', 'weitsichtig', 'weltoffen', 'wissbegierig', 'witzig',
        'wohlwollend', 'zielstrebig', 'zugewandt', 'zuversichtlich', 'zuverlaessig',
    ];

    /**
     * noun | grammatical gender (m/f/n) | lexical family.
     * The themes are distribution metadata, not duplicate/family identifiers.
     */
    private const SUBJECTS = [
        'reisen' => [
            'reisender|m|reise', 'entdecker|m|entdecken', 'weltenbummler|m|welt',
            'globetrotter|m|welt', 'stadtwanderer|m|wandern', 'pfadfinder|m|pfad',
            'kartenleser|m|karte', 'tourenplaner|m|tour', 'rucksacktraeger|m|rucksack',
            'zugreisender|m|reise', 'kuestenwanderer|m|wandern', 'inselkenner|m|insel',
            'reisefreund|m|reise', 'reisefuehrer|m|reise', 'fernwanderer|m|wandern',
            'wegfinder|m|weg', 'ausfluegler|m|ausflug', 'campingfreund|m|camping',
            'herbergsfreund|m|herberge', 'reisebegleiter|m|reise',
        ],
        'musik' => [
            'musikfreund|m|musik', 'gitarrist|m|gitarre', 'pianist|m|klavier',
            'trommler|m|trommel', 'saenger|m|gesang', 'chorfreund|m|chor',
            'komponist|m|komposition', 'melodiensammler|m|melodie', 'geiger|m|geige',
            'bassspieler|m|bass', 'floetenspieler|m|floete', 'taktgeber|m|takt',
            'jazzfreund|m|jazz', 'konzertgaenger|m|konzert', 'klangforscher|m|klang',
            'liedermacher|m|lied', 'musiktalent|n|musik', 'saengerin|f|gesang',
            'harfenspieler|m|harfe', 'rhythmusfreund|m|rhythmus',
        ],
        'literatur' => [
            'leser|m|lesen', 'buecherfreund|m|buch', 'geschichtenerzaehler|m|geschichte',
            'wortsammler|m|wort', 'poet|m|poesie', 'romanleser|m|roman',
            'bibliotheksfreund|m|bibliothek', 'schreiber|m|schreiben', 'vorleser|m|lesen',
            'maerchenfreund|m|maerchen', 'leseratte|f|lesen', 'leseeule|f|lesen',
            'reimefinder|m|reim', 'buchbinder|m|buch', 'chronist|m|chronik',
            'essayist|m|essay', 'sprachtalent|n|sprache', 'leserin|f|lesen',
            'wortakrobat|m|wort', 'geschichtenfreund|m|geschichte',
        ],
        'kunst' => [
            'zeichner|m|zeichnen', 'maler|m|malen', 'fotograf|m|foto',
            'bildhauer|m|bild', 'illustrator|m|illustration', 'skizzenfreund|m|skizze',
            'farbenfreund|m|farbe', 'kunstsammler|m|kunst', 'aquarellmaler|m|malen',
            'keramiker|m|keramik', 'formgestalter|m|form', 'kalligraf|m|schrift',
            'mosaikfreund|m|mosaik', 'bildgestalter|m|bild', 'kunstfreund|m|kunst',
            'zeichnerin|f|zeichnen', 'malerin|f|malen', 'fotografin|f|foto',
            'kunsttalent|n|kunst', 'detailfinder|m|detail',
        ],
        'handwerk' => [
            'bastler|m|basteln', 'tueftler|m|tuefteln', 'werkler|m|werken',
            'drechsler|m|holz', 'schnitzer|m|holz', 'toepfer|m|ton',
            'flechter|m|flechten', 'schneider|m|naehen', 'strickfreund|m|stricken',
            'modellbauer|m|modell', 'holzfreund|m|holz', 'reparierer|m|reparatur',
            'werkzeugkenner|m|werkzeug', 'schmied|m|metall', 'buchgestalter|m|buch',
            'naehfreund|m|naehen', 'bastlerin|f|basteln', 'handwerkstalent|n|handwerk',
            'papiergestalter|m|papier', 'moebelbauer|m|moebel',
        ],
        'technik' => [
            'technikfreund|m|technik', 'programmierer|m|programm', 'pixelgestalter|m|pixel',
            'roboterbauer|m|roboter', 'elektronikfreund|m|elektronik', 'netzwerker|m|netz',
            'systemdenker|m|system', 'datenforscher|m|daten', 'codefreund|m|code',
            'technikbastler|m|technik', 'funkfreund|m|funk', 'konstrukteur|m|konstruktion',
            'schaltungskenner|m|schaltung', 'technikentdecker|m|technik', 'modellpilot|m|modell',
            'digitalkuenstler|m|digital', 'programmiererin|f|programm', 'erfinder|m|erfinden',
            'techniktalent|n|technik', 'rechnerfreund|m|rechner',
        ],
        'sport' => [
            'laeufer|m|laufen', 'radler|m|rad', 'schwimmer|m|schwimmen',
            'kletterer|m|klettern', 'paddler|m|paddeln', 'segler|m|segeln',
            'wanderer|m|wandern', 'ruderer|m|rudern', 'taenzer|m|tanz',
            'sportfreund|m|sport', 'bergsteiger|m|berg', 'ballfreund|m|ball',
            'turner|m|turnen', 'spieler|m|spiel', 'skifahrer|m|ski',
            'kanufreund|m|kanu', 'laeuferin|f|laufen', 'taenzerin|f|tanz',
            'sporttalent|n|sport', 'bewegungsfreund|m|bewegung',
        ],
        'natur' => [
            'naturfreund|m|natur', 'gaertner|m|garten', 'vogelfreund|m|vogel',
            'pflanzenkenner|m|pflanze', 'waldlaeufer|m|wald', 'sterngucker|m|stern',
            'kraeuterfreund|m|kraeuter', 'blumenfreund|m|blume', 'tierfreund|m|tier',
            'naturbeobachter|m|natur', 'pilzkenner|m|pilz', 'imker|m|biene',
            'wetterbeobachter|m|wetter', 'landschaftsfreund|m|landschaft', 'gartenplaner|m|garten',
            'gaertnerin|f|garten', 'naturforscher|m|natur', 'steinfreund|m|stein',
            'spurensucher|m|spur', 'baumkenner|m|baum',
        ],
        'kueche' => [
            'koch|m|kochen', 'baecker|m|backen', 'teefreund|m|tee',
            'kaffeefreund|m|kaffee', 'geniesser|m|genuss', 'gewuerzkenner|m|gewuerz',
            'rezeptesammler|m|rezept', 'brotbaecker|m|brot', 'konditor|m|kuchen',
            'kuechenfreund|m|kueche', 'grillfreund|m|grillen', 'naschfreund|m|naschen',
            'kochfreund|m|kochen', 'obstfreund|m|obst', 'gemuesegaertner|m|gemuese',
            'koechin|f|kochen', 'baeckerin|f|backen', 'kochtalent|n|kochen',
            'suppenfreund|m|suppe', 'geschmackssucher|m|geschmack',
        ],
        'wissen' => [
            'forscher|m|forschen', 'denker|m|denken', 'lerner|m|lernen',
            'raetselfreund|m|raetsel', 'schachfreund|m|schach', 'wissenssammler|m|wissen',
            'knobler|m|knobeln', 'fragensteller|m|frage', 'zahlenfreund|m|zahl',
            'geschichtsfreund|m|geschichte', 'sprachenfreund|m|sprache', 'wissensfinder|m|wissen',
            'astronom|m|astronomie', 'kartograf|m|karte', 'experimentierer|m|experiment',
            'forscherin|f|forschen', 'lernerin|f|lernen', 'allroundtalent|n|vielseitigkeit',
            'entdeckergeist|m|entdecken', 'loesungsfinder|m|loesung',
        ],
        'gemeinschaft' => [
            'helfer|m|helfen', 'zuhoerer|m|zuhoeren', 'teamspieler|m|team',
            'gastgeber|m|gast', 'begleiter|m|begleiten', 'ermutiger|m|mut',
            'vermittler|m|vermitteln', 'nachbar|m|nachbar', 'freund|m|freund',
            'dialogfreund|m|dialog', 'ideenpartner|m|idee', 'mitgestalter|m|gestalten',
            'brueckenbauer|m|bruecke', 'gemeinschaftsfreund|m|gemeinschaft', 'unterstuetzer|m|helfen',
            'helferin|f|helfen', 'gastgeberin|f|gast', 'kommunikationstalent|n|kommunikation',
            'organisator|m|organisation', 'teamdenker|m|team',
        ],
        'alltag' => [
            'morgenmensch|m|morgen', 'abendmensch|m|abend', 'alltagsfreund|m|alltag',
            'spaziergaenger|m|spaziergang', 'sammler|m|sammeln', 'planer|m|plan',
            'beobachter|m|beobachten', 'ideenfinder|m|idee', 'tagtraeumer|m|traum',
            'auszeitfreund|m|auszeit', 'stadtfreund|m|stadt', 'landfreund|m|land',
            'wochenendfreund|m|wochenende', 'fruehaufsteher|m|morgen', 'lebenskuenstler|m|leben',
            'nachtmensch|m|nacht', 'ordnungsfreund|m|ordnung', 'flaneur|m|spaziergang',
            'sammlerin|f|sammeln', 'organisationstalent|n|organisation',
        ],
    ];

    /** Standalone bases deliberately span themes beyond nature imagery. */
    private const ALIASES = [
        'reisen' => ['fernweh', 'reisekompass', 'wegbegleiter', 'ankerplatz', 'hafenblick', 'landkarte', 'reisezeit', 'neuland', 'weitsicht', 'wegweiser', 'inselblick', 'brueckenschlag'],
        'musik' => ['taktgefuehl', 'klangfarbe', 'tonspur', 'melodiebogen', 'notenspiel', 'saitenklang', 'rhythmuszeit', 'klangreise', 'tonleiter', 'zwischenton', 'liedfunke', 'klangraum'],
        'literatur' => ['wortspiel', 'lesefuchs', 'buchfunke', 'lesezeichen', 'zeilenreise', 'wortschatz', 'seitenwechsel', 'geschichtenzeit', 'reimfreude', 'satzzeichen', 'lesestunde', 'wortklang'],
        'kunst' => ['farbenfroh', 'pinselspur', 'farbenspiel', 'skizzenbuch', 'linienflug', 'blickwinkel', 'bildidee', 'lichtpunkt', 'farbton', 'formenspiel', 'augenblick', 'bildreise'],
        'handwerk' => ['werkfreude', 'holzherz', 'handwerkzeit', 'ideenwerk', 'bastelfunke', 'formwerk', 'werkbank', 'papierspur', 'fadenlauf', 'maschenwerk', 'handgemacht', 'feinschliff'],
        'technik' => ['pixelpause', 'bitweise', 'datenblick', 'ideenlabor', 'netzgedanke', 'codefunke', 'schaltplan', 'technikzeit', 'pixelspur', 'digitalblick', 'tueftelzeit', 'klartext'],
        'sport' => ['bewegungsfreude', 'laufmoment', 'radspur', 'gipfelblick', 'wasserlinie', 'teamgeist', 'spielfreude', 'schrittmacher', 'rundenzeit', 'bergpfad', 'segelwind', 'bewegungsraum'],
        'natur' => ['abendrot', 'blattwerk', 'frischebrise', 'gartenzeit', 'grunefeder', 'horizontblick', 'landluft', 'lichtblick', 'mondfeder', 'naturzeit', 'nordlicht', 'regenbogen', 'seebrise', 'sonnenseite', 'sternenblick', 'uferlicht', 'waldfeder', 'waldfreund', 'waldpfad', 'waldzeit', 'wellenklang', 'windspiel', 'wolkenlos'],
        'kueche' => ['kaffeepause', 'teestunde', 'brotfreude', 'gewuerzreise', 'rezeptidee', 'kuechenzeit', 'genussmoment', 'backfreude', 'tassenglueck', 'aromenspiel', 'ofenglueck', 'kostprobe'],
        'wissen' => ['denkraum', 'wissensdurst', 'fragenfunke', 'raetselzeit', 'denkanstoss', 'ideenflug', 'neugierblick', 'klarerblick', 'lernfreude', 'gedankenspiel', 'geistesblitz', 'wissensfunke'],
        'gemeinschaft' => ['miteinander', 'zuversicht', 'herzenssache', 'offenesohr', 'hilfsimpuls', 'guterfaden', 'gedankenaustausch', 'teamfreude', 'gemeinsinn', 'brueckenblick', 'treffpunkt', 'freundschaftsband'],
        'alltag' => ['kleineauszeit', 'kopfkino', 'freiraum', 'luftsprung', 'neustart', 'ruhepol', 'tagtraum', 'tiefgang', 'zeitfenster', 'zeitreise', 'morgenkaffee', 'alltagsfunke'],
    ];

    /** Existing v5 bases stay recognizable and share the same catalog identity. */
    private const LEGACY_ALIASES = [
        'natur' => ['abendfeder', 'abendfunke', 'bergfeder', 'bergfreund', 'bergzeit', 'federleicht', 'funkenflug', 'kustenkind', 'morgenfunke', 'morgenlicht', 'traumfeder', 'waldfunke', 'wanderfunke', 'wellenreiter', 'wolkenfeder', 'wolkenpfad', 'zeichenwind'],
        'reisen' => ['heimathafen', 'pfadfinder', 'reiselust'],
        'literatur' => ['leseratte', 'wortfuchs', 'wortsammler', 'gedankenflug'],
        'kueche' => ['kaffeefunke', 'tassenheld'],
        'alltag' => ['achtsamzeit', 'buntgedacht', 'gluckskind', 'goldmoment', 'seelenruhe', 'stadtfuchs', 'stadtmensch', 'zeitgeist', 'zeitlos'],
    ];

    /** Compound prefixes and compatible heads; not an unrestricted word cross-product. */
    private const COMPOUNDS = [
        'reisen' => [
            ['reise', 'insel', 'hafen', 'kuesten', 'stadt', 'land', 'welt', 'touren', 'karten', 'weg'],
            ['freund', 'entdecker', 'kenner', 'begleiter', 'sammler'],
        ],
        'musik' => [
            ['musik', 'klang', 'ton', 'melodie', 'rhythmus', 'jazz', 'noten', 'lied', 'konzert', 'takt'],
            ['freund', 'entdecker', 'kenner', 'sammler'],
        ],
        'literatur' => [
            ['buch', 'wort', 'reim', 'roman', 'maerchen', 'geschichten', 'zeilen', 'literatur', 'sprachen', 'poesie'],
            ['freund', 'sammler', 'entdecker', 'kenner'],
        ],
        'kunst' => [
            ['kunst', 'farben', 'bilder', 'skizzen', 'formen', 'mosaik', 'aquarell', 'foto', 'detail', 'linien'],
            ['freund', 'sammler', 'entdecker', 'gestalter'],
        ],
        'handwerk' => [
            ['holz', 'papier', 'keramik', 'metall', 'modell', 'faden', 'maschen', 'werkzeug', 'moebel', 'stoff'],
            ['freund', 'kenner', 'gestalter', 'sammler'],
        ],
        'technik' => [
            ['technik', 'pixel', 'daten', 'code', 'funk', 'roboter', 'elektronik', 'rechner', 'netz', 'system'],
            ['freund', 'entdecker', 'kenner', 'tueftler'],
        ],
        'sport' => [
            ['sport', 'lauf', 'rad', 'segel', 'kletter', 'ruder', 'kanu', 'turn', 'ski', 'tanz'],
            ['freund', 'talent', 'begleiter'],
        ],
        'natur' => [
            ['wald', 'garten', 'natur', 'blumen', 'kraeuter', 'voegel', 'baum', 'berg', 'stern', 'wetter'],
            ['freund', 'kenner', 'entdecker', 'beobachter'],
        ],
        'kueche' => [
            ['koch', 'back', 'kaffee', 'tee', 'gewuerz', 'rezept', 'brot', 'kuchen', 'obst', 'genuss'],
            ['freund', 'kenner', 'entdecker'],
        ],
        'wissen' => [
            ['wissen', 'raetsel', 'schach', 'zahlen', 'geschichte', 'sprachen', 'ideen', 'fragen', 'gedanken', 'loesungs'],
            ['freund', 'sammler', 'forscher', 'entdecker'],
        ],
        'gemeinschaft' => [
            ['team', 'ideen', 'dialog', 'gemeinschaft', 'nachbarschaft', 'freundschaft', 'gespraech', 'bruecken'],
            ['freund', 'gestalter', 'begleiter'],
        ],
        'alltag' => [
            ['alltags', 'morgen', 'abend', 'auszeit', 'wochenend', 'pausen', 'ordnungs', 'lebens'],
            ['freund', 'gestalter', 'begleiter'],
        ],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $entries = null;

    public static function version(): string
    {
        return self::VERSION;
    }

    /**
     * @return array<string, array{core_key: string, base: string, tokens: array<string>, family_keys: array<string>, pattern_key: string, theme: string}>
     */
    public static function all(): array
    {
        if (self::$entries !== null) {
            return self::$entries;
        }

        $entries = [];
        $seenBases = [];
        $add = static function (string $key, string $base, array $tokens, array $families, string $pattern, string $theme) use (&$entries, &$seenBases): void {
            if (isset($entries[$key]) || isset($seenBases[$base]) || strlen($base) > 34 || strlen($base) < 4) {
                return;
            }

            $seenBases[$base] = true;
            $entries[$key] = [
                'core_key' => $key,
                'base' => $base,
                'tokens' => $tokens,
                'family_keys' => array_values(array_unique($families)),
                'pattern_key' => $pattern,
                'theme' => $theme,
            ];
        };

        foreach (self::ALIASES as $theme => $aliases) {
            foreach ($aliases as $alias) {
                $add('alias:'.$alias, $alias, [$alias], self::aliasFamilies($alias), 'curated_alias', $theme);
            }
        }

        foreach (self::LEGACY_ALIASES as $theme => $aliases) {
            foreach ($aliases as $alias) {
                $add('alias:'.$alias, $alias, [$alias], self::aliasFamilies($alias), 'curated_alias', $theme);
            }
        }

        foreach (self::SUBJECTS as $theme => $subjects) {
            foreach ($subjects as $subject) {
                [$noun, $gender, $family] = explode('|', $subject);
                $ending = match ($gender) {
                    'f' => 'e',
                    'n' => 'es',
                    default => 'er',
                };

                foreach (self::MODIFIERS as $modifier) {
                    $adjective = self::adjectiveStem($modifier).$ending;
                    $add('pair:'.$modifier.':'.self::canonicalSubject($noun), $adjective.$noun, [$adjective, $noun], [$modifier, $family], 'modifier_subject', $theme);
                }
            }
        }

        foreach (self::COMPOUNDS as $theme => [$prefixes, $heads]) {
            foreach ($prefixes as $prefix) {
                foreach ($heads as $head) {
                    $base = self::compoundPrefix($prefix).$head;
                    $pattern = in_array($theme, ['sport', 'handwerk', 'kueche'], true) ? 'activity_subject' : 'compound';
                    $add('compound:'.$prefix.':'.$head, $base, [$base], [self::familyForPrefix($prefix), $head], $pattern, $theme);
                }
            }
        }

        return self::$entries = $entries;
    }

    /** @return array<string, mixed> */
    public static function statistics(): array
    {
        $entries = self::all();
        $patterns = [];
        $themes = [];
        $families = [];

        foreach ($entries as $entry) {
            $patterns[$entry['pattern_key']] = ($patterns[$entry['pattern_key']] ?? 0) + 1;
            $themes[$entry['theme']] = ($themes[$entry['theme']] ?? 0) + 1;

            foreach ($entry['family_keys'] as $family) {
                $families[$family] = true;
            }
        }

        ksort($patterns);
        ksort($themes);

        return [
            'version' => self::version(),
            'catalog_version' => self::version(),
            'canonical_bases' => count($entries),
            'modifiers' => count(self::MODIFIERS),
            'families' => count($families),
            'themes' => $themes,
            'patterns' => $patterns,
        ];
    }

    /** @return array<string> */
    private static function aliasFamilies(string $alias): array
    {
        $roots = ['wald', 'feder', 'zeit', 'funke', 'wolken', 'licht', 'wort', 'buch', 'reise', 'klang', 'ton', 'takt', 'bild', 'farb', 'werk', 'pixel', 'idee', 'kaffee', 'garten', 'stern', 'berg', 'team', 'morgen', 'abend', 'weg', 'wind', 'natur'];
        $families = [];

        foreach ($roots as $root) {
            if (str_contains($alias, $root)) {
                $families[] = $root === 'farb' ? 'farbe' : $root;
            }
        }

        return $families !== [] ? $families : [$alias];
    }

    private static function familyForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'farben' => 'farbe', 'bilder' => 'bild', 'formen' => 'form',
            'skizzen' => 'skizze', 'linien' => 'linie', 'sprachen' => 'sprache',
            'geschichten' => 'geschichte', 'voegel' => 'vogel', 'blumen' => 'blume',
            'ideen' => 'idee', 'fragen' => 'frage', 'gedanken' => 'denken',
            'zahlen' => 'zahl', 'loesungs' => 'loesung', 'kuesten' => 'kueste',
            'touren' => 'tour', 'karten' => 'karte', 'alltags' => 'alltag',
            'lebens' => 'leben', 'pausen' => 'pause', 'ordnungs' => 'ordnung',
            'bruecken' => 'bruecke',
            default => $prefix,
        };
    }

    private static function adjectiveStem(string $modifier): string
    {
        return match ($modifier) {
            'leise' => 'leis',
            'praezise' => 'praezis',
            'flexibel' => 'flexibl',
            default => $modifier,
        };
    }

    private static function compoundPrefix(string $prefix): string
    {
        return match ($prefix) {
            'wissen' => 'wissens',
            'geschichte' => 'geschichts',
            'gemeinschaft' => 'gemeinschafts',
            'nachbarschaft' => 'nachbarschafts',
            'freundschaft' => 'freundschafts',
            'gespraech' => 'gespraechs',
            default => $prefix,
        };
    }

    /** Gender variants of the same role do not inflate the canonical supply. */
    private static function canonicalSubject(string $noun): string
    {
        return match ($noun) {
            'saengerin' => 'saenger', 'leserin' => 'leser', 'zeichnerin' => 'zeichner',
            'malerin' => 'maler', 'fotografin' => 'fotograf', 'bastlerin' => 'bastler',
            'programmiererin' => 'programmierer', 'laeuferin' => 'laeufer',
            'taenzerin' => 'taenzer', 'gaertnerin' => 'gaertner', 'koechin' => 'koch',
            'baeckerin' => 'baecker', 'forscherin' => 'forscher', 'lernerin' => 'lerner',
            'helferin' => 'helfer', 'gastgeberin' => 'gastgeber', 'sammlerin' => 'sammler',
            default => $noun,
        };
    }
}
