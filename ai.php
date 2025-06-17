<?php

/**
 * Generate a text-based dump of source files or a directory tree structure, suitable for AI processing.
 *
 * (c) 2025 Juergen Schwind <info@juergen-schwind.de>
 * GitHub: https://github.com/jschwind/phpcli-symfony
 *
 * MIT License
 *
 */

function dumpRelevantFiles(
    string $dir,
    array $ignoreExtensions,
    array $globalIgnoreFolders,
    array $rootIgnoreFolders,
    array $globalIgnoreFilenames,
    array $rootIgnoreFilenames,
    string $baseDir,
    $outputHandle
):void {
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir.DIRECTORY_SEPARATOR.$item;
        $relative = ltrim(str_replace($baseDir, '', $path), DIRECTORY_SEPARATOR);

        if (is_dir($path)) {
            if (in_array($item, $globalIgnoreFolders, true) || in_array($relative, $rootIgnoreFolders, true)) {
                continue;
            }
            dumpRelevantFiles(
                $path,
                $ignoreExtensions,
                $globalIgnoreFolders,
                $rootIgnoreFolders,
                $globalIgnoreFilenames,
                $rootIgnoreFilenames,
                $baseDir,
                $outputHandle
            );
        } else {
            if (in_array($item, $globalIgnoreFilenames, true) || (strpos(
                        $relative,
                        DIRECTORY_SEPARATOR
                    ) === false && in_array($item, $rootIgnoreFilenames, true))) {
                continue;
            }
            if (in_array(pathinfo($item, PATHINFO_EXTENSION), $ignoreExtensions, true)) {
                continue;
            }

            $content = file_get_contents($path);
            fwrite($outputHandle, str_repeat('-', 64).PHP_EOL);
            fwrite($outputHandle, "FILE: $relative".PHP_EOL);
            fwrite($outputHandle, "---".PHP_EOL);
            fwrite($outputHandle, $content.PHP_EOL);
            fwrite($outputHandle, str_repeat('-', 64).PHP_EOL);
            fwrite($outputHandle, PHP_EOL);
        }
    }
}

function printTree(
    string $dir,
    string $prefix = '',
    array $ignoreExtensions = [],
    array $globalIgnoreFolders = [],
    array $rootIgnoreFolders = [],
    array $globalIgnoreFilenames = [],
    array $rootIgnoreFilenames = [],
    string $baseDir = '',
    $outputFile = null
):void {
    $items = array_values(array_diff(scandir($dir), ['.', '..']));
    $count = count($items);

    foreach ($items as $index => $item) {
        $path = $dir.DIRECTORY_SEPARATOR.$item;
        $relative = ltrim(str_replace($baseDir, '', $path), DIRECTORY_SEPARATOR);

        if (is_dir($path) && (in_array($item, $globalIgnoreFolders, true) || in_array(
                    $relative,
                    $rootIgnoreFolders,
                    true
                ))) {
            continue;
        }
        if (in_array($item, $globalIgnoreFilenames, true) || (is_file($path) && strpos(
                    $relative,
                    DIRECTORY_SEPARATOR
                ) === false && in_array($item, $rootIgnoreFilenames, true))) {
            continue;
        }
        if (is_file($path) && in_array(pathinfo($item, PATHINFO_EXTENSION), $ignoreExtensions, true)) {
            continue;
        }

        $isLast = $index === $count - 1;
        $connector = $isLast?'└── ':'├── ';
        $line = $prefix.$connector.$item.(is_dir($path)?'/':'').PHP_EOL;

        if ($outputFile) {
            fwrite($outputFile, $line);
        } else {
            echo $line;
        }

        if (is_dir($path)) {
            $newPrefix = $prefix.($isLast?'    ':'│   ');
            printTree(
                $path,
                $newPrefix,
                $ignoreExtensions,
                $globalIgnoreFolders,
                $rootIgnoreFolders,
                $globalIgnoreFilenames,
                $rootIgnoreFilenames,
                $baseDir,
                $outputFile
            );
        }
    }
}

$args = $argv;
array_shift($args);
$mode = 'dump';

if (in_array('--tree', $args, true)) {
    $mode = 'tree';
    $args = array_values(array_filter($args, fn($a) => $a !== '--tree'));
}

if (count($args) === 0) {
    $inputDir = getcwd();
    $outputPath = null;
} elseif (count($args) === 1) {
    if (is_dir($args[0])) {
        $inputDir = realpath($args[0]);
        $outputPath = null;
    } else {
        $inputDir = getcwd();
        $outputPath = $args[0];
    }
} else {
    $inputDir = realpath($args[0]);
    $outputPath = $args[1];
}

if (!$inputDir || !is_dir($inputDir)) {
    echo "Invalid directory: $inputDir".PHP_EOL;
    exit(1);
}

// === Ignore Configuration ===
$ignoreExtensions = [
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
$ignoreFolders = [
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
$ignoreFilenames = [
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

$aiJsonPath = $inputDir.DIRECTORY_SEPARATOR.'ai.json';
if (is_readable($aiJsonPath) && ($json = json_decode(file_get_contents($aiJsonPath), true))) {
    if (isset($json['ignoreExtensions']) && is_array($json['ignoreExtensions'])) {
        $ignoreExtensions = $json['ignoreExtensions'];
    }
    if (isset($json['ignoreFolders']) && is_array($json['ignoreFolders'])) {
        $ignoreFolders = $json['ignoreFolders'];
    }
    if (isset($json['ignoreFilenames']) && is_array($json['ignoreFilenames'])) {
        $ignoreFilenames = $json['ignoreFilenames'];
    }
}

$globalIgnoreFolders = [];
$rootIgnoreFolders = [];
foreach ($ignoreFolders as $f) {
    if (str_starts_with($f, '/')) {
        $rootIgnoreFolders[] = ltrim($f, '/');
    } else {
        $globalIgnoreFolders[] = $f;
    }
}
$globalIgnoreFilenames = [];
$rootIgnoreFilenames = [];
foreach ($ignoreFilenames as $f) {
    if (str_starts_with($f, '/')) {
        $rootIgnoreFilenames[] = ltrim($f, '/');
    } else {
        $globalIgnoreFilenames[] = $f;
    }
}

$projectName = basename($inputDir);

if ($outputPath) {
    $handle = fopen($outputPath, 'w');
    if (!$handle) {
        echo "Could not open file for writing: $outputPath".PHP_EOL;
        exit(1);
    }

    if ($mode === 'tree') {
        fwrite($handle, 'AI-ready directory tree for the project "'.$projectName.'"'.PHP_EOL);
        fwrite($handle, str_repeat('=', 64).PHP_EOL.PHP_EOL);
        fwrite($handle, basename($inputDir).'/'.PHP_EOL);
        printTree(
            $inputDir,
            '',
            $ignoreExtensions,
            $globalIgnoreFolders,
            $rootIgnoreFolders,
            $globalIgnoreFilenames,
            $rootIgnoreFilenames,
            $inputDir,
            $handle
        );
        fwrite($handle, PHP_EOL.str_repeat('=', 64).PHP_EOL);
        fwrite($handle, 'End of AI-ready directory tree for the project "'.$projectName.'"'.PHP_EOL.PHP_EOL);
        fwrite($handle, 'Remember this project as "'.$projectName.'" and wait for further instructions.'.PHP_EOL);
    } else {
        fwrite($handle, 'AI-ready file output for the project "'.$projectName.'"'.PHP_EOL);
        fwrite($handle, str_repeat('=', 64).PHP_EOL.PHP_EOL);
        dumpRelevantFiles(
            $inputDir,
            $ignoreExtensions,
            $globalIgnoreFolders,
            $rootIgnoreFolders,
            $globalIgnoreFilenames,
            $rootIgnoreFilenames,
            $inputDir,
            $handle
        );
        fwrite($handle, str_repeat('=', 64).PHP_EOL);
        fwrite($handle, 'End of AI-ready file output for the project "'.$projectName.'"'.PHP_EOL.PHP_EOL);
        fwrite($handle, 'Remember this project as "'.$projectName.'" and wait for further instructions.'.PHP_EOL);
    }

    fclose($handle);
    echo "AI-ready output written to: $outputPath".PHP_EOL;
} else {
    if ($mode === 'tree') {
        echo 'AI-ready directory tree for the project "'.$projectName.'"'.PHP_EOL;
        echo str_repeat('=', 64).PHP_EOL.PHP_EOL;
        echo basename($inputDir).'/'.PHP_EOL;
        printTree(
            $inputDir,
            '',
            $ignoreExtensions,
            $globalIgnoreFolders,
            $rootIgnoreFolders,
            $globalIgnoreFilenames,
            $rootIgnoreFilenames,
            $inputDir
        );
        echo PHP_EOL.str_repeat('=', 64).PHP_EOL;
        echo 'End of AI-ready directory tree for the project "'.$projectName.'"'.PHP_EOL.PHP_EOL;
        echo 'Remember this project as "'.$projectName.'" and wait for further instructions.';
    } else {
        echo 'AI-ready file output for the project "'.$projectName.'"'.PHP_EOL;
        echo str_repeat('=', 64).PHP_EOL.PHP_EOL;
        dumpRelevantFiles(
            $inputDir,
            $ignoreExtensions,
            $globalIgnoreFolders,
            $rootIgnoreFolders,
            $globalIgnoreFilenames,
            $rootIgnoreFilenames,
            $inputDir,
            STDOUT
        );
        echo str_repeat('=', 64).PHP_EOL;
        echo 'End of AI-ready file output for the project "'.$projectName.'"'.PHP_EOL.PHP_EOL;
        echo 'Remember this project as "'.$projectName.'" and wait for further instructions.';
    }
}
