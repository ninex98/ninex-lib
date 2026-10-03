<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use think\App;
use think\console\{Input, Output};

class ConsoleTest extends \PHPUnit\Framework\TestCase
{
    public function testStandardServiceDiscoveryRegistersNativeCommands(): void
    {
        $root = sys_get_temp_dir().'/ninex-think-console-'.bin2hex(random_bytes(8)).'/';
        mkdir($root.'vendor/composer', 0777, true);
        mkdir($root.'app');
        $manifest = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
        file_put_contents($root.'composer.json', '{"require":{"topthink/framework":"^8.1"}}');
        file_put_contents($root.'vendor/composer/installed.json', json_encode(['packages' => [
            ['name' => 'ninex/lib','version' => '2.0.0','extra' => $manifest['extra']],
            ['name' => 'topthink/framework','version' => '8.1.4'], ['name' => 'topthink/think-orm','version' => '4.0.51'],
        ]]));
        $factory = function () use ($root) {
            $app = new class ($root) extends App {
                protected $initializers = [\think\initializer\RegisterService::class, \think\initializer\BootService::class];
            };
            $app->config->set(['default' => 'file','stores' => ['file' => ['type' => 'File','path' => $root.'runtime/cache/']]], 'cache');
            $app->initialize();
            return $app;
        };
        try {
            $first = $factory();
            $first->console->call('service:discover');
            $this->assertContains(\Ninex\Lib\ThinkPhp\ServiceProvider::class, require $root.'vendor/services.php');
            $app = $factory();
            $make = $app->console->find('ninexlib:make-crud');
            $preview = new Output('buffer');
            $this->assertSame(0, $make->run(new Input(['ninexlib:make-crud','NativeProduct','--fields=name:string,status:boolean','--dry-run']), $preview));
            $this->assertFileDoesNotExist($root.'app/service/NativeProductService.php');
            if (!is_dir($app->getRuntimePath())) {
                mkdir($app->getRuntimePath(), 0777, true);
            }
            file_put_contents($app->getRuntimePath().'route.php', '<?php return [];');
            $this->assertSame(0, $make->run(new Input(['ninexlib:make-crud','NativeProduct','--fields=name:string,status:boolean']), new Output('buffer')));
            $this->assertFileExists($root.'app/service/NativeProductService.php');
            $this->assertFileDoesNotExist($app->getRuntimePath().'route.php');
            $this->assertSame(1, $make->run(new Input(['ninexlib:make-crud','NativeProduct','--fields=name:string,status:boolean']), new Output('buffer')));
            $doctor = $app->console->find('ninexlib:doctor');
            $this->assertSame(0, $doctor->run(new Input(['ninexlib:doctor','--strict']), new Output('buffer')));
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
