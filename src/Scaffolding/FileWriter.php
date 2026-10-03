<?php

namespace Ninex\Lib\Scaffolding;

use RuntimeException;
use Throwable;

final class FileWriter
{
    /** Preflight the entire plan, then create files exclusively. Never overwrite existing application code. */
    public function write(string $project, array $files): void
    {
        $root = realpath($project);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Project directory does not exist.');
        }
        foreach ($files as $relative => $contents) {
            if (!preg_match('/^[A-Za-z0-9_\/.-]+$/D', $relative) || str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
                throw new RuntimeException('Invalid output path.');
            }
            $path = $root;
            $segments = explode('/', $relative);
            foreach ($segments as $position => $segment) {
                $path .= DIRECTORY_SEPARATOR.$segment;
                if ($position < count($segments) - 1 && file_exists($path) && !is_dir($path)) {
                    throw new RuntimeException('Output parent is not a directory: '.$relative);
                }
                if (is_link($path)) {
                    throw new RuntimeException('Refusing to write through symlink: '.$relative);
                }
            }
            if (file_exists($path)) {
                throw new RuntimeException('File already exists: '.$relative.'. No files were changed.');
            }
            if (str_ends_with($relative, '.php')) {
                token_get_all($contents, TOKEN_PARSE);
            }
        }
        $created = [];
        try {
            foreach ($files as $relative => $contents) {
                $path = $root.DIRECTORY_SEPARATOR.$relative;
                if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
                    throw new RuntimeException('Cannot create output directory.');
                }
                $handle = @fopen($path, 'x');
                if ($handle === false) {
                    throw new RuntimeException('Cannot exclusively create: '.$relative);
                }
                $created[] = $path;
                try {
                    $written = 0;
                    while ($written < strlen($contents)) {
                        $bytes = fwrite($handle, substr($contents, $written));
                        if ($bytes === false || $bytes === 0) {
                            throw new RuntimeException('Incomplete write: '.$relative);
                        }
                        $written += $bytes;
                    }
                } finally {
                    fclose($handle);
                }
            }
        } catch (Throwable $e) {
            foreach ($created as $path) {
                unlink($path);
            }
            throw $e;
        }
    }
}
