<?php

namespace Ninex\Lib\Scaffolding;

/** Read-only diagnostics: never boots the target application or runs its PHP files. */
final class ProjectDoctor
{
    public function packages(string $project, ?string $vendorOverride = null): array
    {
        $manifest = json_decode(@file_get_contents($project.'/composer.json') ?: '{}', true) ?: [];
        $vendor = $vendorOverride ?? (getenv('COMPOSER_VENDOR_DIR') ?: ($manifest['config']['vendor-dir'] ?? 'vendor'));
        $vendor = str_starts_with($vendor, '/') ? $vendor : $project.'/'.$vendor;
        $installed = json_decode(@file_get_contents($vendor.'/composer/installed.json') ?: '[]', true) ?: [];
        $packages = [];
        foreach ($installed['packages'] ?? $installed as $package) {
            if (is_array($package) && isset($package['name'])) {
                $packages[$package['name']] = $package['version'] ?? 'unknown';
            }
        }
        return $packages;
    }

    public function detectFramework(string $project, ?string $vendorOverride = null): ?string
    {
        $manifest = json_decode(@file_get_contents($project.'/composer.json') ?: '{}', true) ?: [];
        $names = array_merge(array_keys($manifest['require'] ?? []), array_keys($this->packages($project, $vendorOverride)));
        $laravel = in_array('laravel/framework', $names, true);
        $think = in_array('topthink/framework', $names, true);
        if ($laravel && $think) {
            throw new \InvalidArgumentException('Multiple frameworks found; specify --framework explicitly.');
        }
        return $laravel ? 'laravel' : ($think ? 'thinkphp' : null);
    }

    /** Diagnostics are advisory, not a proof of business compatibility. */
    public function inspect(string $project, string $framework, ?string $vendorOverride = null): array
    {
        if (!in_array($framework, ['core', 'laravel', 'thinkphp'], true)) {
            throw new \InvalidArgumentException('Unknown framework.');
        }
        $checks = [];
        $add = static function ($level, $message) use (&$checks) {
            $checks[] = compact('level', 'message');
        };
        $add(PHP_VERSION_ID >= 80100 ? 'ok' : 'error', 'CLI PHP '.PHP_VERSION.' ('.PHP_BINARY.')');
        $add(function_exists('token_get_all') ? 'ok' : 'error', 'tokenizer is required for generation and source diagnostics');
        if (!is_file($project.'/composer.json')) {
            $add('error', 'No composer.json in the selected project.');
        }
        $packages = $this->packages($project, $vendorOverride);
        $dependency = ['laravel' => 'laravel/framework', 'thinkphp' => 'topthink/framework'][$framework] ?? null;
        if ($dependency !== null) {
            $add(isset($packages[$dependency]) ? 'ok' : 'error', $dependency.': '.($packages[$dependency] ?? 'not installed; run composer install'));
            if (isset($packages[$dependency])) {
                $supported = $framework === 'laravel' ? '/^v?(10|11|12|13)\./' : '/^v?8\.1\./';
                if (!preg_match($supported, $packages[$dependency])) {
                    $add('warning', 'This framework version is outside the tested integration range.');
                }
            }
        }
        if ($framework === 'thinkphp') {
            $add(isset($packages['topthink/think-orm']) ? 'ok' : 'error', 'topthink/think-orm: '.($packages['topthink/think-orm'] ?? 'not installed'));
        }
        $add(is_writable($project) ? 'ok' : 'warning', 'Project root writable for generation: '.(is_writable($project) ? 'yes' : 'no'));
        if ($framework === 'laravel' && glob($project.'/bootstrap/cache/routes*.php')) {
            $add('warning', 'Routes are cached. Artisan generation clears the cache; standalone generation requires php artisan route:clear.');
        }
        if ($framework === 'laravel' && function_exists('token_get_all') && is_dir($project.'/app')) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($project.'/app', \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile() || $file->isLink() || $file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($project) + 1);
                $source = file_get_contents($file->getPathname());
                try {
                    $tokens = token_get_all($source, TOKEN_PARSE);
                } catch (\ParseError $e) {
                    $add('error', $relative.': PHP syntax error: '.$e->getMessage());
                    continue;
                }
                $code = '';
                foreach ($tokens as $token) {
                    $code .= is_array($token) ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true) ? ' ' : $token[1]) : $token;
                }
                // Only flag changed query semantics; legacy model writes, validation and Policy remain optional.
                $base = 'Ninex\\Lib\\Http\\Services\\LibService';
                $alias = 'LibService';
                if (preg_match('/use\s+'.preg_quote($base, '/').'\s+as\s+(\w+)\s*;/', $code, $match)) {
                    $alias = $match[1];
                }
                if (!preg_match('/\babstract\s+class\b/', $code)
                    && preg_match('/extends\s+(?:'.preg_quote($alias, '/').'|\\\\?'.preg_quote($base, '/').')\b/', $code)
                    && !preg_match('/\$allowedFilters\s*=|function\s+scopeQuery\s*\(/', $code)) {
                    $add('warning', $relative.': review $allowedFilters before passing request filters to paginate/all.');
                }
                if (preg_match('/\bwithTableLock\s*\(/', $code)) {
                    $add('warning', $relative.': withTableLock is disabled; use an explicit row lock/business transaction.');
                }
                if (str_contains($code, 'SqlRecord::$sql')) {
                    $add('warning', $relative.': replace SqlRecord::$sql with app(SqlRecord::class)->all().');
                }
            }
        }
        return $checks;
    }
}
