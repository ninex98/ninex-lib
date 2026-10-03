<?php

require __DIR__.'/release-metadata.php';

$options = getopt('', ['tag:', 'attempts:', 'interval:']);
try {
    $metadata = ninexReleaseMetadata($options['tag'] ?? '');
    $attempts = filter_var($options['attempts'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 40]]);
    $interval = filter_var($options['interval'] ?? 15, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 30]]);
    if ($attempts === false || $interval === false) {
        throw new InvalidArgumentException('Invalid retry settings.');
    }
    $context = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: ninex-lib-release-check\r\nCache-Control: no-cache\r\n"]]);
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $response = @file_get_contents('https://repo.packagist.org/p2/ninex/lib.json', false, $context);
        $data = $response === false ? [] : json_decode($response, true);
        foreach ($data['packages']['ninex/lib'] ?? [] as $package) {
            if (strtolower(ltrim($package['version'], 'vV')) === strtolower($metadata['version'])) {
                echo 'Packagist contains ninex/lib '.$metadata['version'].PHP_EOL;
                exit(0);
            }
        }
        echo 'Waiting for Packagist sync ('.$attempt.'/'.$attempts.')'.PHP_EOL;
        if ($attempt < $attempts) {
            sleep($interval);
        }
    }
    throw new RuntimeException('Version is not visible on Packagist yet. Check the existing GitHub webhook or use Update on the Packagist package page.');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(1);
}
