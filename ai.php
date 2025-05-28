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
    array $ignoreFolders,
    array $ignoreFilenames,
    string $baseDir,
    $outputHandle
):void {
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir.DIRECTORY_SEPARATOR.$item;
        $relative = ltrim(str_replace($baseDir, '', $path), DIRECTORY_SEPARATOR);

        if (is_dir($path)) {
            if (in_array($item, $ignoreFolders)) {
                continue;
            }
            dumpRelevantFiles($path, $ignoreExtensions, $ignoreFolders, $ignoreFilenames, $baseDir, $outputHandle);
        } else {
            if (in_array($item, $ignoreFilenames)) {
                continue;
            }
            if (in_array(pathinfo($item, PATHINFO_EXTENSION), $ignoreExtensions)) {
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
    array $ignoreFolders = [],
    array $ignoreFilenames = [],
    string $baseDir = '',
    $outputFile = null
):void {
    $items = array_diff(scandir($dir), ['.', '..']);
    $items = array_values($items);
    $count = count($items);

    foreach ($items as $index => $item) {
        $path = $dir.DIRECTORY_SEPARATOR.$item;

        if (is_dir($path) && in_array($item, $ignoreFolders)) {
            continue;
        }
        if (in_array($item, $ignoreFilenames)) {
            continue;
        }
        if (is_file($path) && in_array(pathinfo($item, PATHINFO_EXTENSION), $ignoreExtensions)) {
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
            printTree($path, $newPrefix, $ignoreExtensions, $ignoreFolders, $ignoreFilenames, $baseDir, $outputFile);
        }
    }
}

// === Argument Parsing ===
$args = $argv;
array_shift($args); // remove script name
$mode = 'dump';

if (in_array('--tree', $args)) {
    $mode = 'tree';
    $args = array_filter($args, fn($a) => $a !== '--tree');
    $args = array_values($args);
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
    'log',
    'tmp',
    'jpg',
    'jpeg',
    'png',
    'gif',
    'svg',
    'ico',
    'zip',
    'exe',
    'dll',
    'bin',
    'pdf',
    'gz',
    'tar',
];
$ignoreFolders = [
    'vendor',
    '.git',
    'node_modules',
    '.idea',
    'data',
    'docker',
    'tests',
    'test',
    'build',
    'dist',
    'public',
    'var',
    'storage',
    'cache',
    'logs',
    'tmp',
    'docs',
    'doc',
    'examples',
    'example',
    'src/Tests',
    'src/Test'
];
$ignoreFilenames = [
    'ai.php',
    'ai.txt',
    '.env.local',
    '.gitignore',
    '.gitattributes',
    '.gitlab-ci.yml',
    'composer.json',
    'composer.lock',
    'docker-compose.yml',
    'LICENSE',
    'CHANGELOG.md',
    'CONTRIBUTING.md',
    'phpunit.xml',
    'phpunit.xml.dist',
    'phpstan.neon',
    'phpstan.neon.dist',
    'rector.php',
    'rector.php.dist'
];
$aiJsonPath = $inputDir . DIRECTORY_SEPARATOR . 'ai.json';
if (file_exists($aiJsonPath) && is_readable($aiJsonPath)) {
    $jsonData = json_decode(file_get_contents($aiJsonPath), true);
    if (is_array($jsonData)) {
        if (isset($jsonData['ignoreExtensions']) && is_array($jsonData['ignoreExtensions'])) {
            $ignoreExtensions = $jsonData['ignoreExtensions'];
        }
        if (isset($jsonData['ignoreFolders']) && is_array($jsonData['ignoreFolders'])) {
            $ignoreFolders = $jsonData['ignoreFolders'];
        }
        if (isset($jsonData['ignoreFilenames']) && is_array($jsonData['ignoreFilenames'])) {
            $ignoreFilenames = $jsonData['ignoreFilenames'];
        }
    }
}

// === Main Logic ===
if ($outputPath) {
    $handle = fopen($outputPath, 'w');
    if (!$handle) {
        echo "Could not open file for writing: $outputPath".PHP_EOL;
        exit(1);
    }

    if ($mode === 'tree') {
        fwrite($handle, basename($inputDir).'/'.PHP_EOL);
        printTree($inputDir, '', $ignoreExtensions, $ignoreFolders, $ignoreFilenames, $inputDir, $handle);
    } else {
        dumpRelevantFiles($inputDir, $ignoreExtensions, $ignoreFolders, $ignoreFilenames, $inputDir, $handle);
    }

    fclose($handle);
    echo "AI-ready file output written to: $outputPath".PHP_EOL;
} else {
    if ($mode === 'tree') {
        echo basename($inputDir).'/'.PHP_EOL;
        printTree($inputDir, '', $ignoreExtensions, $ignoreFolders, $ignoreFilenames, $inputDir);
    } else {
        dumpRelevantFiles($inputDir, $ignoreExtensions, $ignoreFolders, $ignoreFilenames, $inputDir, STDOUT);
    }
}
