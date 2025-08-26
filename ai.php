<?php

declare(strict_types=1);

/**
 * AIProjectDumper - A tool to prepare projects for AI analysis.
 *
 * (c) 2025 Juergen Schwind <info@juergen-schwind.de>
 * GitHub: https://github.com/jschwind/phpcli-ai
 *
 * MIT License
 *
 */

namespace AIDumper;

use RuntimeException;

final class AIProjectDumper
{
    private string $projectDir;
    private string $mode;
    private ?string $outputPath;
    private string $projectRootName;

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
        $this->projectDir = realpath($projectDir)?:throw new RuntimeException("Invalid project directory: $projectDir");
        $this->mode = $mode;
        $this->outputPath = $outputPath;
        $this->projectRootName = basename($this->projectDir);
        $this->loadConfig($configPath);
    }

    private function loadConfig(?string $configPath):void
    {
        $this->excludeExtensions = [
            'bin',
            'dll',
            'exe',
            'gif',
            'gz',
            'ico',
            'jpeg',
            'jpg',
            'log',
            'pdf',
            'png',
            'svg',
            'tar',
            'tmp',
            'zip',
        ];

        $this->excludeFolders = [
            '.git',
            '.idea',
            'build',
            'bin',
            'cache',
            'data',
            'doc',
            'docs',
            'dist',
            'docker',
            'example',
            'examples',
            'logs',
            'node_modules',
            'public',
            'src/Test',
            'src/Tests',
            'storage',
            'test',
            'tests',
            'tmp',
            'vendor',
            'var',
        ];

        $this->excludeFilenames = [
            '.env.local',
            '.gitattributes',
            '.gitignore',
            '.gitlab-ci.yml',
            'CHANGELOG.md',
            'CONTRIBUTING.md',
            'LICENSE',
            'ai.json',
            'ai.php',
            'ai.txt',
            'composer.json',
            'composer.lock',
            'docker-compose.yml',
            'package-lock.json',
            'phpunit.xml',
            'phpunit.xml.dist',
            'phpstan.neon',
            'phpstan.neon.dist',
            'rector.php',
            'rector.php.dist',
            'symfony.lock',
        ];

        $path = $configPath??$this->projectDir.DIRECTORY_SEPARATOR.'ai.json';
        if (!is_readable($path)) {
            return;
        }

        $json = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->excludeExtensions = $json['exclude']['extensions']??$this->excludeExtensions;
        $this->excludeFolders = $json['exclude']['folders']??$this->excludeFolders;
        $this->excludeFilenames = $json['exclude']['filenames']??$this->excludeFilenames;

        $this->includeExtensions = $json['include']['extensions']??[];
        $this->includeFolders = $json['include']['folders']??[];
        $this->includeFilenames = $json['include']['filenames']??[];
    }

    public function run():void
    {
        $outputHandle = $this->outputPath?fopen($this->outputPath, 'w'):STDOUT;

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
            echo "AI-ready output written to: $this->outputPath".PHP_EOL;
        }
    }

    private function writeHeader($handle, string $name):void
    {
        fwrite(
            $handle,
            "AI-ready ".($this->mode === 'tree'?"directory tree":"file output")." for the project \"$name\"\n"
        );
        fwrite($handle, str_repeat('=', 64)."\n\n");
        if ($this->mode === 'tree') {
            fwrite($handle, basename($this->projectDir)."/\n");
        }
    }

    private function writeFooter($handle, string $name):void
    {
        fwrite($handle, str_repeat('=', 64)."\n");
        fwrite(
            $handle,
            "End of AI-ready ".($this->mode === 'tree'?"directory tree":"file output")." for the project \"$name\"\n\n"
        );
        fwrite($handle, "Remember this project as \"$name\" and wait for further instructions.\n");
    }

    private function shouldInclude(string $relativePath, string $filename, bool $isDir):bool
    {
        // Exclude by filename (supports absolute-like "/path" entries and substring matches)
        foreach ($this->excludeFilenames as $exFile) {
            if (str_starts_with($exFile, '/')) {
                if ('/'.$relativePath === $exFile) {
                    return false;
                }
            } elseif (str_contains($relativePath, $exFile)) {
                return false;
            }
        }

        // Helper to check whether a path is inside or on the way to any of the include folders
        $isWithinInclude = function(string $relPath, bool $dir) : bool {
            if (empty($this->includeFolders)) {
                return true;
            }
            $path = ltrim($relPath, '/');
            $path = str_replace('\\', '/', $path);

            // For files, compare the directory part
            if (!$dir) {
                $lastSlash = strrpos($path, '/');
                $pathDir = $lastSlash !== false ? substr($path, 0, $lastSlash) : '';
                foreach ($this->includeFolders as $inc) {
                    $inc = trim(str_replace('\\', '/', $inc), '/');
                    if ($inc === '') { continue; }
                    if ($pathDir !== '' && str_starts_with($pathDir, $inc)) {
                        return true;
                    }
                }
                return false;
            }

            // For directories, allow if the directory is a parent of, equal to, or inside an include folder
            foreach ($this->includeFolders as $inc) {
                $inc = trim(str_replace('\\', '/', $inc), '/');
                if ($inc === '') { continue; }
                if ($path === '' || $path === '.') {
                    // root level: only traverse into parents of include folders
                    if (str_contains($inc, '/')) {
                        return true; // we need to descend from root
                    }
                }
                if ($path === $inc || str_starts_with($inc, $path.'/') || str_starts_with($path, $inc.'/')) {
                    return true;
                }
                // Also allow exact segment when includeFolders contains only a top-level segment name
                if ($path === $inc) {
                    return true;
                }
            }
            return false;
        };

        // Directory include logic: traverse only within the include scope when includeFolders are provided
        if ($isDir) {
            return $isWithinInclude($relativePath, true);
        }

        // File include logic
        $hasAnyInclude = !empty($this->includeExtensions) || !empty($this->includeFolders) || !empty($this->includeFilenames);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        // Normalize relative path to forward slashes without leading slash for matching
        $relNorm = ltrim(str_replace('\\', '/', $relativePath), '/');
        $incFiles = array_map(function($f){ return ltrim(str_replace('\\', '/', (string)$f), '/'); }, $this->includeFilenames);
        $matchByName = false;
        if (!empty($incFiles)) {
            // Match by:
            // - exact basename
            // - exact relative path
            // - exact relative path when config provided with leading slash
            $matchByName = in_array($filename, $incFiles, true)
                || in_array($relNorm, $incFiles, true)
                || in_array('/'.$relNorm, $this->includeFilenames, true);
        }
        $matchByExt = !empty($this->includeExtensions) && in_array($ext, $this->includeExtensions, true);
        $within = $isWithinInclude($relativePath, false);

        if (!$hasAnyInclude) {
            return true;
        }

        if (!empty($this->includeFolders)) {
            return $within && ($matchByName || $matchByExt);
        }

        return $matchByName || $matchByExt;
    }

    private function hasIncludedChildren(string $path, string $relativePrefix = ''):bool
    {
        $items = array_diff(scandir($path)?:[], ['.', '..']);

        foreach ($items as $item) {
            $fullPath = $path.DIRECTORY_SEPARATOR.$item;
            $relative = ltrim($relativePrefix.$item, DIRECTORY_SEPARATOR);
            $isDir = is_dir($fullPath);

            if ($isDir) {
                if ($this->isExcludedFolder($relative, $item)) {
                    continue;
                }

                if ($this->hasIncludedChildren($fullPath, $relative.'/')) {
                    return true;
                }
            } else {
                $ext = pathinfo($item, PATHINFO_EXTENSION);
                if (in_array($ext, $this->excludeExtensions, true)) {
                    continue;
                }

                if (!$this->shouldInclude($relative, $item, false)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private function isExcludedFolder(string $relativePath, string $folderName):bool
    {
        foreach ($this->excludeFolders as $excluded) {
            if (str_starts_with($excluded, '/')) {
                if ('/'.$relativePath === $excluded) {
                    return true;
                }
            } elseif ($folderName === $excluded) {
                return true;
            }
        }

        return false;
    }

    private function dumpFiles(string $dir, $handle, string $relativePrefix = ''):void
    {
        foreach (scandir($dir)?:[] as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;
            $relative = ltrim($relativePrefix.$item, DIRECTORY_SEPARATOR);
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

            if ($isDir) {
                // For directories, allow traversal if either the directory itself matches includeFolders
                // or it has any included children deeper in the tree.
                if (!$this->shouldInclude($relative, $item, true) && !$this->hasIncludedChildren($path, $relative.'/')) {
                    continue;
                }
                $this->dumpFiles($path, $handle, $relative.'/');
            } else {
                if (!$this->shouldInclude($relative, $item, false)) {
                    continue;
                }
                fwrite($handle, str_repeat('-', 64)."\n");
                fwrite($handle, "FILE: $relative\n");
                fwrite($handle, "---\n");
                fwrite($handle, file_get_contents($path)."\n");
                fwrite($handle, str_repeat('-', 64)."\n\n");
            }
        }
    }

    private function printTree(string $dir, string $prefix, $handle, string $relativePrefix = ''):void
    {
        $rawItems = array_diff(scandir($dir)?:[], ['.', '..']);

        $items = [];

        foreach ($rawItems as $item) {
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            $relative = ltrim($relativePrefix.$item, DIRECTORY_SEPARATOR);
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

            if ($isDir) {
                // Keep directories that either match includeFolders or have included children,
                // so the tree shows the path down to included files.
                if (!$this->shouldInclude($relative, $item, true) && !$this->hasIncludedChildren($path, $relative.'/')) {
                    continue;
                }
            } else {
                if (!$this->shouldInclude($relative, $item, false)) {
                    continue;
                }
            }

            $items[] = ['name' => $item, 'path' => $path, 'relative' => $relative, 'isDir' => $isDir];
        }

        $count = count($items);

        foreach ($items as $index => $entry) {
            if ($entry['isDir'] && !$this->hasIncludedChildren($entry['path'], $entry['relative'].'/')) {
                continue;
            }

            $isLast = $index === $count - 1;
            $connector = $isLast?'└── ':'├── ';
            fwrite($handle, $prefix.$connector.$entry['name'].($entry['isDir']?'/':'')."\n");

            if ($entry['isDir']) {
                $newPrefix = $prefix.($isLast?'    ':'│   ');
                $this->printTree($entry['path'], $newPrefix, $handle, $entry['relative'].'/');
            }
        }
    }
}

$mode = in_array('--tree', $argv, true)?'tree':'dump';
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
    $inputDir = is_dir($dirCandidate)?realpath($dirCandidate):getcwd();
    $outputPath = $fileCandidate;
}

$dumper = new AIProjectDumper($inputDir, $mode, $outputPath, $configPath);
$dumper->run();