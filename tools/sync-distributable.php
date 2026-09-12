<?php

declare(strict_types=1);

const DISTRIBUTABLE_DIRECTORY = 'upload';

function trackedPaths(string $repositoryRoot, string $pathspec): array
{
    $command = sprintf('git -C %s ls-files %s', escapeshellarg($repositoryRoot), escapeshellarg($pathspec));
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, sprintf("Could not list the tracked files under %s.\n", $pathspec));
        exit(2);
    }

    return array_values(array_filter($output, static fn (string $path): bool => $path !== ''));
}

/**
 * Compares content rather than bytes: the two trees have drifted apart on line endings, and a
 * comparison that counted those would report every shared file on every run and be ignored.
 */
function hasSameContent(string $firstPath, string $secondPath): bool
{
    $first = str_replace("\r\n", "\n", (string) file_get_contents($firstPath));
    $second = str_replace("\r\n", "\n", (string) file_get_contents($secondPath));

    return $first === $second;
}

/**
 * Splits the distributable into the files this tool can speak for and the files it cannot. Only a
 * path under version control in both trees is comparable — the distributable also carries built
 * assets that are gitignored at the root, and copying a working-tree file over one of those would
 * publish whatever happened to be on the machine that ran this.
 */
function inspectDistributable(string $repositoryRoot): array
{
    $rootTrackedPaths = array_flip(trackedPaths($repositoryRoot, ':(exclude)' . DISTRIBUTABLE_DIRECTORY));

    $divergent = [];
    $uncomparable = [];

    foreach (trackedPaths($repositoryRoot, DISTRIBUTABLE_DIRECTORY) as $distributablePath) {
        $sourcePath = substr($distributablePath, strlen(DISTRIBUTABLE_DIRECTORY) + 1);

        if (!isset($rootTrackedPaths[$sourcePath])) {
            $uncomparable[] = $sourcePath;
            continue;
        }

        if (!hasSameContent($repositoryRoot . '/' . $sourcePath, $repositoryRoot . '/' . $distributablePath)) {
            $divergent[] = $sourcePath;
        }
    }

    return ['divergent' => $divergent, 'uncomparable' => $uncomparable];
}

function describeUncomparable(array $uncomparable): void
{
    if ($uncomparable === []) {
        return;
    }

    echo sprintf("\n%d file(s) in %s/ are not tracked at the repository root and were not compared:\n", count($uncomparable), DISTRIBUTABLE_DIRECTORY);
    foreach ($uncomparable as $sourcePath) {
        echo sprintf("  %s\n", $sourcePath);
    }
    echo "\nBuilt assets belong here — the distributable ships them so operators need no build step.\nKeeping them current is a release concern, not something copying a file can settle.\n";
}

function reportCheck(array $inspection): int
{
    if ($inspection['divergent'] === []) {
        echo "Every shared file in " . DISTRIBUTABLE_DIRECTORY . "/ matches the source tree.\n";
        describeUncomparable($inspection['uncomparable']);
        return 0;
    }

    fwrite(STDERR, sprintf("%d file(s) in %s/ have drifted from the source tree:\n\n", count($inspection['divergent']), DISTRIBUTABLE_DIRECTORY));
    foreach ($inspection['divergent'] as $sourcePath) {
        fwrite(STDERR, sprintf("  %s\n", $sourcePath));
    }
    fwrite(STDERR, "\nRun: php tools/sync-distributable.php\n");

    return 1;
}

function copyOverDistributable(string $repositoryRoot, array $inspection): int
{
    foreach ($inspection['divergent'] as $sourcePath) {
        copy($repositoryRoot . '/' . $sourcePath, $repositoryRoot . '/' . DISTRIBUTABLE_DIRECTORY . '/' . $sourcePath);
        echo sprintf("Updated %s/%s\n", DISTRIBUTABLE_DIRECTORY, $sourcePath);
    }

    echo sprintf("\n%d file(s) copied into %s/.\n", count($inspection['divergent']), DISTRIBUTABLE_DIRECTORY);
    describeUncomparable($inspection['uncomparable']);

    return 0;
}

$repositoryRoot = dirname(__DIR__);
$isCheckOnly = in_array('--check', array_slice($argv, 1), true);
$inspection = inspectDistributable($repositoryRoot);

exit($isCheckOnly ? reportCheck($inspection) : copyOverDistributable($repositoryRoot, $inspection));
