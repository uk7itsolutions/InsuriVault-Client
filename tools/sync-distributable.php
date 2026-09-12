<?php

declare(strict_types=1);

const DISTRIBUTABLE_DIRECTORY = 'upload';

/**
 * Every file tracked under upload/ is a copy of one at the repository root. The distributable
 * decides which files ship; the root decides what is in them. Nothing here chooses between the two,
 * so a file the distributable does not carry is simply not its business.
 */
function trackedDistributablePaths(string $repositoryRoot): array
{
    $command = sprintf('git -C %s ls-files %s', escapeshellarg($repositoryRoot), escapeshellarg(DISTRIBUTABLE_DIRECTORY));
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "Could not list the tracked files under " . DISTRIBUTABLE_DIRECTORY . "/.\n");
        exit(2);
    }

    return array_values(array_filter($output, static fn (string $path): bool => $path !== ''));
}

/**
 * Compares content rather than bytes: the two trees have drifted apart on line endings, and a
 * comparison that counted those would report all 130 files on every run and be ignored within a
 * week.
 */
function hasSameContent(string $firstPath, string $secondPath): bool
{
    $first = str_replace("\r\n", "\n", (string) file_get_contents($firstPath));
    $second = str_replace("\r\n", "\n", (string) file_get_contents($secondPath));

    return $first === $second;
}

function findDivergentPaths(string $repositoryRoot, array $distributablePaths): array
{
    $divergent = [];

    foreach ($distributablePaths as $distributablePath) {
        $sourcePath = substr($distributablePath, strlen(DISTRIBUTABLE_DIRECTORY) + 1);
        $absoluteSource = $repositoryRoot . '/' . $sourcePath;
        $absoluteDistributable = $repositoryRoot . '/' . $distributablePath;

        if (!is_file($absoluteSource)) {
            $divergent[] = ['source' => $sourcePath, 'distributable' => $distributablePath, 'reason' => 'no counterpart at the repository root'];
            continue;
        }

        if (!hasSameContent($absoluteSource, $absoluteDistributable)) {
            $divergent[] = ['source' => $sourcePath, 'distributable' => $distributablePath, 'reason' => 'content differs'];
        }
    }

    return $divergent;
}

function reportCheck(array $divergent): int
{
    if ($divergent === []) {
        echo "The distributable matches the source tree.\n";
        return 0;
    }

    fwrite(STDERR, sprintf("%d file(s) in %s/ have drifted from the source tree:\n\n", count($divergent), DISTRIBUTABLE_DIRECTORY));
    foreach ($divergent as $entry) {
        fwrite(STDERR, sprintf("  %s — %s\n", $entry['source'], $entry['reason']));
    }
    fwrite(STDERR, "\nRun: php tools/sync-distributable.php\n");

    return 1;
}

function copyOverDistributable(string $repositoryRoot, array $divergent): int
{
    $copied = 0;

    foreach ($divergent as $entry) {
        $absoluteSource = $repositoryRoot . '/' . $entry['source'];

        if (!is_file($absoluteSource)) {
            fwrite(STDERR, sprintf("Skipped %s — %s. Remove it from the distributable, or restore it at the root.\n", $entry['distributable'], $entry['reason']));
            continue;
        }

        copy($absoluteSource, $repositoryRoot . '/' . $entry['distributable']);
        echo sprintf("Updated %s\n", $entry['distributable']);
        $copied++;
    }

    echo sprintf("\n%d file(s) copied into %s/.\n", $copied, DISTRIBUTABLE_DIRECTORY);

    return $copied === count($divergent) ? 0 : 1;
}

$repositoryRoot = dirname(__DIR__);
$isCheckOnly = in_array('--check', array_slice($argv, 1), true);
$divergent = findDivergentPaths($repositoryRoot, trackedDistributablePaths($repositoryRoot));

exit($isCheckOnly ? reportCheck($divergent) : copyOverDistributable($repositoryRoot, $divergent));
