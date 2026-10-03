<?php

/** Parse tags once for both CI validation and local release checks. */
function ninexReleaseMetadata(string $tag): array
{
    if (!preg_match('/^v?((?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*))(?:-((?:alpha|beta|rc)[.]?[0-9]+))?$/iD', $tag, $matches)) {
        throw new InvalidArgumentException('Expected a release tag such as v2.0.0 or v2.0.0-rc.1.');
    }
    return ['tag' => $tag, 'version' => ltrim($tag, 'vV'), 'prerelease' => isset($matches[2])];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        echo json_encode(ninexReleaseMetadata($argv[1] ?? ''), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage().PHP_EOL);
        exit(1);
    }
}
