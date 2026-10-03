<?php

/** Install the built ZIPs into three fresh consumers without source symlinks or dev dependencies. */
$options = getopt('', ['composer:', 'repository:', 'version:']);
require __DIR__.'/release-metadata.php';
$root = dirname(__DIR__);
$repository = realpath($options['repository'] ?? $root.'/build/packages/packages.json');
$composer = $options['composer'] ?? getenv('NINEX_COMPOSER_BINARY');
$version = ninexReleaseMetadata($options['version'] ?? '2.1.0')['version'];
$stability = preg_match('/-(alpha|beta|rc)/i', $version, $match)
    ? ['alpha' => 'alpha', 'beta' => 'beta', 'rc' => 'RC'][strtolower($match[1])]
    : 'stable';
if (!$repository || !$composer || !is_file($composer)) {
    throw new InvalidArgumentException('Build archives first and pass --composer=/absolute/path/to/composer.');
}
$base = $root.'/build/consumers-'.bin2hex(random_bytes(6));
mkdir($base, 0777, true);
$report = ['repository_sha256' => hash_file('sha256', $repository), 'version' => $version, 'php' => PHP_VERSION, 'directory' => $base, 'consumers' => []];
$run = function (array $command, string $cwd, string $log): void {
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $cwd);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Consumer command failed; see '.$log);
    }
};
foreach (['core' => 'ninex/lib', 'laravel' => 'ninex/lib', 'thinkphp' => 'ninex/lib'] as $label => $package) {
    $directory = $base.'/'.$label;
    mkdir($directory);
    $url = 'file://'.implode('/', array_map('rawurlencode', explode('/', dirname($repository))));
    $manifest = [
        'name' => 'ninex-test/'.$label, 'autoload' => ['psr-4' => [$label === 'thinkphp' ? 'app\\' : 'App\\' => 'app/']], 'require' => [$package => $version],
        'repositories' => [['type' => 'composer', 'url' => $url]],
        'minimum-stability' => $stability, 'prefer-stable' => true,
        'config' => ['allow-plugins' => false],
    ];
    if ($label === 'laravel') {
        $manifest['require']['laravel/framework'] = PHP_VERSION_ID < 80200 ? '^10.0' : (PHP_VERSION_ID < 80300 ? '^12.0' : '^13.0');
    }
    if ($label === 'thinkphp') {
        $manifest['require']['topthink/framework'] = '^8.1';
    }
    file_put_contents($directory.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    $log = $directory.'/verification.log';
    $run([PHP_BINARY, $composer, 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--no-progress', '--no-scripts'], $directory, $log);
    foreach (glob($directory.'/vendor/ninex/*') as $installed) {
        if (is_link($installed)) {
            throw new RuntimeException('Expected ZIP installation, not a source symlink.');
        }
    }
    $run([PHP_BINARY, $composer, 'check-platform-reqs', '--no-dev'], $directory, $log);
    $run([PHP_BINARY, $root.'/tools/consumer-smoke/'.$label.'.php'], $directory, $log);
    $run([PHP_BINARY, $directory.'/vendor/bin/ninex', '--help'], $directory, $log);
    $report['consumers'][$label] = ['status' => 'passed', 'packages' => array_column(json_decode(file_get_contents($directory.'/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR)['packages'], 'version', 'name')];
    echo $label.": fresh ZIP consumer passed\n";
}
file_put_contents($root.'/build/consumer-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
echo "Verification logs: {$base}\n";
