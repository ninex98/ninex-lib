<?php

/** Build one Composer archive without committing, tagging or publishing anything. */
$options = getopt('', ['version:', 'output:', 'ref:']);
require __DIR__.'/release-metadata.php';
$version = ninexReleaseMetadata($options['version'] ?? '2.1.0')['version'];
$root = dirname(__DIR__);
$output = $options['output'] ?? $root.'/build/packages';
if (!is_dir($output) && !mkdir($output, 0777, true)) {
    throw new RuntimeException('Cannot create output directory.');
}
$output = realpath($output);
$archive = $output.'/ninex-lib-'.$version.'.zip';
$zip = new ZipArchive();
if (isset($options['ref'])) {
    // CI verifies the same Git export rules and original manifest used by tagged distributions.
    $process = proc_open(['git', 'archive', '--format=zip', '--output='.$archive, $options['ref']], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start git archive.');
    }
    fclose($pipes[0]);
    if (proc_close($process) !== 0 || $zip->open($archive) !== true) {
        throw new RuntimeException('Cannot archive the selected Git ref.');
    }
    $manifest = json_decode($zip->getFromName('composer.json'), true, 512, JSON_THROW_ON_ERROR);
} else {
    // Local review can include uncommitted changes; the consumer manifest is kept intact.
    if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create archive.');
    }
    $files = [];
    foreach (['src', 'config', 'resources', 'bin', 'docs', 'examples', 'composer.json', 'README.md', 'CHANGELOG.md', 'LICENSE'] as $relative) {
        $path = $root.'/'.$relative;
        if (is_file($path)) {
            $files[$relative] = $path;
        } else {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isLink()) {
                    throw new RuntimeException('Refusing to archive a source symlink.');
                }
                $name = substr($file->getPathname(), strlen($root) + 1);
                if ($file->isFile()) {
                    $files[$name] = $file->getPathname();
                }
            }
        }
    }
    ksort($files);
    foreach ($files as $name => $path) {
        $zip->addFile($path, $name);
        if ($name === 'bin/ninex') {
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100755 << 16);
        }
        $zip->setMtimeName($name, 315532800);
    }
    $manifest = json_decode(file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
}
if ($manifest['name'] !== 'ninex/lib') {
    throw new RuntimeException('Expected the ninex/lib package.');
}
if (!$zip->close()) {
    throw new RuntimeException('Cannot finish archive.');
}
$manifest['version'] = $version;
$manifest['dist'] = ['type' => 'zip', 'url' => 'file://'.implode('/', array_map('rawurlencode', explode('/', $archive))), 'shasum' => hash_file('sha1', $archive)];
file_put_contents($output.'/packages.json', json_encode(['packages' => ['ninex/lib' => [$version => $manifest]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
file_put_contents($output.'/SHA256SUMS', hash_file('sha256', $archive).'  '.basename($archive)."\n");
echo basename($archive)."\nLocal Composer repository: {$output}/packages.json\n";
