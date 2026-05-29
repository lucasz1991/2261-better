<?php

namespace App\Livewire\Admin\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\Process\Process;

class FormScriptTests extends Component
{
    public string $nodeBinary = '';
    public string $npmBinary = '';
    public string $scriptPath = '';
    public string $arguments = '';
    public string $targetUrl = '';
    public int $timeoutSeconds = 60;
    public bool $appendTargetUrl = true;

    /** @var array<int, array<string, mixed>> */
    public array $environmentChecks = [];

    /** @var array<string, mixed>|null */
    public ?array $lastRun = null;

    public function mount(): void
    {
        Gate::authorize('form_tests.run');

        $this->nodeBinary = $this->resolveExecutable('node', 'node');
        $this->npmBinary = $this->resolveExecutable($this->npmCommand(), 'npm');
        $this->targetUrl = (string) config('app.url', 'http://localhost');
        $this->scriptPath = $this->availableScripts()[0]['path'] ?? '';
    }

    public function runEnvironmentCheck(): void
    {
        $this->validate([
            'nodeBinary' => ['required', 'string', 'max:255'],
            'npmBinary' => ['required', 'string', 'max:255'],
        ]);

        $nodeBinary = $this->resolveExecutable($this->nodeBinary, 'node');
        $npmBinary = $this->resolveExecutable($this->npmBinary, 'npm');

        $this->environmentChecks = [
            $this->runQuickCommand('Node', [$nodeBinary, '--version']),
            $this->runQuickCommand('NPM', [$npmBinary, '--version']),
            [
                'label' => 'Scripts',
                'ok' => count($this->availableScripts()) > 0,
                'message' => count($this->availableScripts()).' Node-Script(s) unter resources/node gefunden.',
                'output' => collect($this->availableScripts())->pluck('path')->implode("\n"),
            ],
        ];
    }

    public function showHelp(): void
    {
        $this->arguments = '--help';
        $this->appendTargetUrl = false;
        $this->runScript();
    }

    public function prepareScreenshotRun(): void
    {
        $this->arguments = '';
        $this->appendTargetUrl = true;
        $this->clearOutput();
    }

    public function runScript(): void
    {
        $this->validate([
            'nodeBinary' => ['required', 'string', 'max:255'],
            'scriptPath' => ['required', 'string', 'max:255'],
            'arguments' => ['nullable', 'string', 'max:2000'],
            'targetUrl' => ['nullable', 'url', 'max:255'],
            'timeoutSeconds' => ['required', 'integer', 'min:5', 'max:300'],
            'appendTargetUrl' => ['boolean'],
        ]);

        $absoluteScriptPath = $this->resolveScriptPath($this->scriptPath);

        if (! $absoluteScriptPath) {
            $this->lastRun = [
                'ok' => false,
                'exit_code' => null,
                'duration_ms' => 0,
                'command' => '',
                'stdout' => '',
                'stderr' => 'Script wurde nicht gefunden oder liegt nicht unter resources/node.',
                'ran_at' => now()->format('Y-m-d H:i:s'),
            ];

            return;
        }

        $args = $this->parseArguments($this->arguments);

        if ($this->appendTargetUrl && $this->targetUrl !== '' && ! $this->hasUrlArgument($args)) {
            $args[] = '--url='.$this->targetUrl;
        }

        $nodeBinary = $this->resolveExecutable($this->nodeBinary, 'node');
        $command = array_merge([$nodeBinary, $absoluteScriptPath], $args);
        $process = new Process($command, base_path(), $this->nodeProcessEnvironment());
        $process->setTimeout($this->timeoutSeconds);

        $startedAt = microtime(true);
        $process->run();

        $stdout = $this->limitOutput($process->getOutput());
        $stderr = $this->limitOutput($process->getErrorOutput());
        $previewImage = $this->extractPreviewImage($stdout);

        $this->lastRun = [
            'ok' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'command' => $this->displayCommand(array_merge([$nodeBinary, $this->scriptPath], $args)),
            'stdout' => $stdout,
            'stderr' => $stderr,
            'preview_image_url' => $previewImage['url'] ?? null,
            'preview_image_path' => $previewImage['path'] ?? null,
            'ran_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public function clearOutput(): void
    {
        $this->lastRun = null;
        $this->environmentChecks = [];
    }

    /**
     * @return array<int, array{path: string, label: string}>
     */
    public function availableScripts(): array
    {
        $root = resource_path('node');

        if (! File::isDirectory($root)) {
            return [];
        }

        $scripts = [];

        foreach (File::allFiles($root) as $file) {
            if (! in_array($file->getExtension(), ['js', 'cjs', 'mjs'], true)) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getRelativePathname());
            $scripts[] = [
                'path' => $path,
                'label' => $path,
            ];
        }

        usort($scripts, fn (array $a, array $b) => $a['path'] <=> $b['path']);

        return $scripts;
    }

    public function render()
    {
        return view('livewire.admin.tests.form-script-tests', [
            'scripts' => $this->availableScripts(),
        ])->layout('layouts.master');
    }

    /**
     * @param array<int, string> $command
     * @return array<string, mixed>
     */
    private function runQuickCommand(string $label, array $command): array
    {
        $process = new Process($command, base_path(), $this->nodeProcessEnvironment());
        $process->setTimeout(10);
        $process->run();

        $output = trim($process->getOutput() ?: $process->getErrorOutput());

        return [
            'label' => $label,
            'ok' => $process->isSuccessful(),
            'message' => $process->isSuccessful() ? 'OK' : 'Fehler',
            'output' => $output,
        ];
    }

    private function npmCommand(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
    }

    /**
     * Apache/XAMPP may not expose TEMP/TMP to PHP. Puppeteer needs those paths
     * for temporary files, but Chrome should keep the normal Windows profile
     * environment to avoid launch hangs under Symfony Process.
     *
     * @return array<string, string>
     */
    private function nodeProcessEnvironment(): array
    {
        $tempPath = storage_path('app/temp/node');

        File::ensureDirectoryExists($tempPath);

        return [
            'TEMP' => $tempPath,
            'TMP' => $tempPath,
            'TMPDIR' => $tempPath,
        ];
    }

    private function resolveExecutable(string $configuredBinary, string $binaryType): string
    {
        $configuredBinary = trim($configuredBinary);

        foreach ($this->executableCandidates($configuredBinary, $binaryType) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $configuredBinary !== '' ? $configuredBinary : $binaryType;
    }

    /**
     * @return array<int, string>
     */
    private function executableCandidates(string $configuredBinary, string $binaryType): array
    {
        $candidates = [];

        if ($configuredBinary !== '') {
            $candidates[] = $configuredBinary;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $programFiles = array_filter([
                getenv('ProgramFiles') ?: null,
                getenv('ProgramFiles(x86)') ?: null,
                'C:\\Program Files',
                'C:\\Program Files (x86)',
            ]);

            foreach ($programFiles as $directory) {
                $candidates[] = rtrim($directory, '\\/').'\\nodejs\\'.($binaryType === 'npm' ? 'npm.cmd' : 'node.exe');
            }
        }

        $candidates[] = $binaryType === 'npm' ? $this->npmCommand() : 'node';

        return array_values(array_unique($candidates));
    }

    private function resolveScriptPath(string $relativePath): ?string
    {
        $normalizedPath = str_replace('\\', '/', trim($relativePath));

        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            return null;
        }

        $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['js', 'cjs', 'mjs'], true)) {
            return null;
        }

        $root = realpath(resource_path('node'));
        $candidate = realpath(resource_path('node/'.$normalizedPath));

        if (! $root || ! $candidate) {
            return null;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/').'/';
        $candidate = str_replace('\\', '/', $candidate);

        if (! str_starts_with($candidate, $root)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return array<int, string>
     */
    private function parseArguments(?string $arguments): array
    {
        $arguments = trim((string) $arguments);

        if ($arguments === '') {
            return [];
        }

        preg_match_all('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\']*)\'|\\S+/', $arguments, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn (array $match): string => stripcslashes($match[1] ?? $match[2] ?? $match[0] ?? ''))
            ->filter(fn (string $argument): bool => $argument !== '')
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $arguments
     */
    private function hasUrlArgument(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === '--url' || str_starts_with($argument, '--url=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $command
     */
    private function displayCommand(array $command): string
    {
        return collect($command)
            ->map(function (string $part): string {
                if (str_contains($part, ' ')) {
                    return '"'.$part.'"';
                }

                return $part;
            })
            ->implode(' ');
    }

    private function limitOutput(string $output): string
    {
        $output = trim($output);

        if (strlen($output) <= 20000) {
            return $output;
        }

        return substr($output, 0, 20000)."\n\n... Ausgabe gekuerzt ...";
    }

    /**
     * @return array{url: string, path: string}|null
     */
    private function extractPreviewImage(string $stdout): ?array
    {
        $result = json_decode($stdout, true);

        if (! is_array($result)) {
            $result = $this->extractJsonObject($stdout);
        }

        $paths = [];

        if (is_array($result)) {
            foreach (['absoluteScreenshotPath', 'screenshotPath'] as $key) {
                if (! empty($result[$key]) && is_string($result[$key])) {
                    $paths[] = $result[$key];
                }
            }
        }

        if (preg_match_all("#(?:[A-Za-z]:)?[^\\r\\n\"']*storage[\\\\/]app[\\\\/]public[\\\\/]screenshots[\\\\/]regulierungs-check[\\\\/][^\\r\\n\"']+\\.png#i", $stdout, $matches)) {
            $paths = array_merge($paths, $matches[0]);
        }

        foreach (array_unique($paths) as $path) {
            $preview = $this->buildPreviewImage($path);

            if ($preview) {
                return $preview;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJsonObject(string $output): ?array
    {
        $start = strpos($output, '{');
        $end = strrpos($output, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($output, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{url: string, path: string}|null
     */
    private function buildPreviewImage(string $path): ?array
    {
        $normalizedPath = str_replace('\\', '/', trim($path));

        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            return null;
        }

        $absolutePath = preg_match('/^[A-Za-z]:\\//', $normalizedPath) === 1
            ? $normalizedPath
            : base_path($normalizedPath);

        $absolutePath = realpath($absolutePath);
        $basePath = realpath(storage_path('app/public/screenshots/regulierungs-check'));

        if (! $absolutePath || ! $basePath) {
            return null;
        }

        $absolutePath = str_replace('\\', '/', $absolutePath);
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/').'/';

        if (! str_starts_with($absolutePath, $basePath) || ! str_ends_with(strtolower($absolutePath), '.png')) {
            return null;
        }

        $relativePath = ltrim(substr($absolutePath, strlen($basePath)), '/');

        return [
            'url' => route('admin.form-script-tests.screenshot', ['path' => $relativePath]),
            'path' => 'storage/app/public/screenshots/regulierungs-check/'.$relativePath,
        ];
    }
}
