<?php

declare(strict_types=1);

namespace AIDumper;

use RuntimeException;

final class AIProjectDumper
{
    private string $projectDir;
    private string $mode;
    private ?string $outputPath;

    private array $includeExtensions = [];
    private array $includeFolders = [];
    private array $includeFilenames = [];

    private array $excludeExtensions = [];
    private array $excludeFolders = [];
    private array $excludeFilenames = [];

    public function __construct(
        string $projectDir,
        string $mode = 'dump',
        ?string $outputPath = null,
        ?string $configPath = null
    ) {
        $this->projectDir = realpath($projectDir) ?: throw new RuntimeException("Invalid project directory: $projectDir");
        $this->mode = $mode;
        $this->outputPath = $outputPath;
        $this->loadConfig($configPath);
    }

    private function loadConfig(?string $configPath): void
    {
        $path = $configPath ?? $this->projectDir . DIRECTORY_SEPARATOR . 'ai.json';
        if (!is_readable($path)) {
            return;
        }

        $json = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->excludeExtensions = $json['exclude']['extensions'] ?? [];
        $this->excludeFolders = $json['exclude']['folders'] ?? [];
        $this->excludeFilenames = $json['exclude']['filenames'] ?? [];

        $this->includeExtensions = $json['include']['extensions'] ?? [];
        $this->includeFolders = $json['include']['folders'] ?? [];
        $this->includeFilenames = $json['include']['filenames'] ?? [];
    }

    public function run(): void
    {
        $outputHandle = $this->outputPath ? fopen($this->outputPath, 'w') : STDOUT;

        if (!$outputHandle) {
            throw new RuntimeException("Failed to open output path: {$this->outputPath}");
        }

        $projectName = basename($this->projectDir);

        $this->writeHeader($outputHandle, $projectName);

        if ($this->mode === 'tree') {
            $this->printTree($this->projectDir, '', $outputHandle);
        } else {
            $this->dumpFiles($this->projectDir, $outputHandle);
        }

        $this->writeFooter($outputHandle, $projectName);

        if (is_resource($outputHandle) && $outputHandle !== STDOUT) {
            fclose($outputHandle);
        }
    }

    private function writeHeader($handle, string $name): void
    {
        fwrite($handle, "AI-ready " . ($this->mode === 'tree' ? "directory tree" : "file output") . " for the project \"$name\"\n");
        fwrite($handle, str_repeat('=', 64) . "\n\n");
        if ($this->mode === 'tree') {
            fwrite($handle, basename($this->projectDir) . "/\n");
        }
    }

    private function writeFooter($handle, string $name): void
    {
        fwrite($handle, str_repeat('=', 64) . "\n");
        fwrite($handle, "End of AI-ready " . ($this->mode === 'tree' ? "directory tree" : "file output") . " for the project \"$name\"\n\n");
        fwrite($handle, "Remember this project as \"$name\" and wait for further instructions.\n");
    }

    private function shouldInclude(string $relativePath, string $filename, bool $isDir): bool
    {
        foreach ($this->excludeFilenames as $exFile) {
            if (str_starts_with($exFile, '/')) {
                if ('/' . $relativePath === $exFile) {
                    return false;
                }
            } elseif (str_contains($relativePath, $exFile)) {
                return false;
            }
        }

        if ($isDir) {
            return empty($this->includeFolders) || in_array($filename, $this->includeFolders, true);
        }

        if (!empty($this->includeFilenames) && !in_array($filename, $this->includeFilenames, true)) {
            return false;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return empty($this->includeExtensions) || in_array($ext, $this->includeExtensions, true);
    }

    private function hasIncludedChildren(string $path, string $relativePrefix = ''): bool
    {
        $items = array_diff(scandir($path) ?: [], ['.', '..']);

        foreach ($items as $item) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            $relative = ltrim($relativePrefix . $item, DIRECTORY_SEPARATOR);
            $isDir = is_dir($fullPath);

            if ($isDir && $this->isExcludedFolder($relative, $item)) {
                continue;
            }

            if (!$isDir) {
                $ext = pathinfo($item, PATHINFO_EXTENSION);
                if (in_array($ext, $this->excludeExtensions, true)) {
                    continue;
                }
            }

            if (!$this->shouldInclude($relative, $item, $isDir)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function isExcludedFolder(string $relativePath, string $folderName): bool
    {
        foreach ($this->excludeFolders as $excluded) {
            if (str_starts_with($excluded, '/')) {
                if ('/' . $relativePath === $excluded) {
                    return true;
                }
            } elseif ($folderName === $excluded) {
                return true;
            }
        }

        return false;
    }

    private function dumpFiles(string $dir, $handle, string $relativePrefix = ''): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $relative = ltrim($relativePrefix . $item, DIRECTORY_SEPARATOR);
            $isDir = is_dir($path);

            if ($isDir && $this->isExcludedFolder($relative, $item)) {
                continue;
            }

            if (!$isDir) {
                $ext = pathinfo($item, PATHINFO_EXTENSION);
                if (in_array($ext, $this->excludeExtensions, true)) {
                    continue;
                }
            }

            if (!$this->shouldInclude($relative, $item, $isDir)) {
                continue;
            }

            if ($isDir) {
                $this->dumpFiles($path, $handle, $relative . '/');
            } else {
                fwrite($handle, str_repeat('-', 64) . "\n");
                fwrite($handle, "FILE: $relative\n");
                fwrite($handle, "---\n");
                fwrite($handle, file_get_contents($path) . "\n");
                fwrite($handle, str_repeat('-', 64) . "\n\n");
            }
        }
    }

    private function printTree(string $dir, string $prefix, $handle, string $relativePrefix = ''): void
    {
        $rawItems = array_diff(scandir($dir) ?: [], ['.', '..']);

        $items = [];

        foreach ($rawItems as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $relative = ltrim($relativePrefix . $item, DIRECTORY_SEPARATOR);
            $isDir = is_dir($path);

            if ($isDir && $this->isExcludedFolder($relative, $item)) {
                continue;
            }

            if (!$isDir) {
                $ext = pathinfo($item, PATHINFO_EXTENSION);
                if (in_array($ext, $this->excludeExtensions, true)) {
                    continue;
                }
            }

            if (!$this->shouldInclude($relative, $item, $isDir)) {
                continue;
            }

            $items[] = ['name' => $item, 'path' => $path, 'relative' => $relative, 'isDir' => $isDir];
        }

        $count = count($items);

        foreach ($items as $index => $entry) {
            if ($entry['isDir'] && !$this->hasIncludedChildren($entry['path'], $entry['relative'] . '/')) {
                continue;
            }

            $isLast = $index === $count - 1;
            $connector = $isLast ? '└── ' : '├── ';
            fwrite($handle, $prefix . $connector . $entry['name'] . ($entry['isDir'] ? '/' : '') . "\n");

            if ($entry['isDir']) {
                $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $this->printTree($entry['path'], $newPrefix, $handle, $entry['relative'] . '/');
            }
        }
    }
}

$mode = in_array('--tree', $argv, true) ? 'tree' : 'dump';
$config = '--config=';

$configPath = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, $config)) {
        $configPath = substr($arg, strlen($config));
    }
}

$inputDir = getcwd();
$outputPath = null;

$args = array_values(array_filter($argv, fn($arg) => !str_starts_with($arg, '--')));
$paths = array_values(array_filter($argv, fn($arg) => !str_starts_with($arg, '--')));

if (count($paths) === 2) {
    [$script, $possibleDirOrFile] = $paths;

    if (is_dir($possibleDirOrFile)) {
        $inputDir = realpath($possibleDirOrFile);
    } else {
        $outputPath = $possibleDirOrFile;
    }
}

if (count($paths) === 3) {
    [, $dirCandidate, $fileCandidate] = $paths;
    $inputDir = is_dir($dirCandidate) ? realpath($dirCandidate) : getcwd();
    $outputPath = $fileCandidate;
}

$dumper = new AIProjectDumper($inputDir, $mode, $outputPath, $configPath);
$dumper->run();