<?php

namespace Ninex\Lib\Tests;

use Ninex\Lib\Scaffolding\{ConsoleApplication, CrudGenerator, FileWriter, ResourceDefinition};
use PHPUnit\Framework\TestCase;

class GeneratorTest extends TestCase
{
    private string $project;
    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/ninex-generator-'.bin2hex(random_bytes(8));
        mkdir($this->project);
    }
    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->project);
    }

    public function testCliPreviewWritesNothingAndSecondGenerationPreservesEdits(): void
    {
        $lines = [];
        $output = function ($line) use (&$lines) {
            $lines[] = $line;
        };
        $args = ['make:crud', 'Product', '--framework=laravel', '--fields=name:string,status:boolean,note:text?', '--path='.$this->project];
        $cli = new ConsoleApplication();
        $this->assertSame(0, $cli->run([...$args, '--dry-run'], $output));
        $this->assertSame([], glob($this->project.'/*'));
        $this->assertSame(0, $cli->run($args, $output));
        $service = $this->project.'/app/Services/ProductService.php';
        file_put_contents($service, file_get_contents($service)."\n// application customization\n");
        $before = hash_file('sha256', $service);
        $this->assertSame(1, $cli->run($args, $output));
        $this->assertSame($before, hash_file('sha256', $service));
        $this->assertCount(1, glob($this->project.'/database/migrations/*_create_products_table.php'));
    }

    public function testInvalidDefinitionsCannotWriteCodeOrSql(): void
    {
        foreach ([['../Escape', 'name:string', null], ['Match', 'name:string', null], ['Product', 'name:string,id:integer', null], ['Product', 'name:string,name:text', null], ['Product', 'payload:unknown', null], ['Product', 'name:string', 'products;DROP TABLE users']] as [$name, $fields, $table]) {
            try {
                new ResourceDefinition($name, 'laravel', $fields, $table);
                $this->fail('Invalid definition accepted');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame([], glob($this->project.'/*'));
    }

    public function testConflictIsCheckedBeforeAnyFileIsWritten(): void
    {
        mkdir($this->project.'/app/Http/Controllers', 0777, true);
        file_put_contents($this->project.'/app/Http/Controllers/ProductController.php', 'existing');
        $plan = (new CrudGenerator())->plan(new ResourceDefinition('Product', 'laravel', 'name:string'), $this->project);
        try {
            (new FileWriter())->write($this->project, $plan);
            $this->fail('Expected conflict');
        } catch (\RuntimeException) {
            $this->assertFileDoesNotExist($this->project.'/app/Models/Product.php');
        }
        $this->assertSame('existing', file_get_contents($this->project.'/app/Http/Controllers/ProductController.php'));
    }

    public function testGeneratorRejectsSymlinkParents(): void
    {
        mkdir($this->project.'/external');
        symlink($this->project.'/external', $this->project.'/app');
        $plan = (new CrudGenerator())->plan(new ResourceDefinition('Product', 'laravel', 'name:string'), $this->project);
        $this->expectException(\RuntimeException::class);
        (new FileWriter())->write($this->project, $plan);
    }

    public function testBothRenderersProduceParsablePhpForEverySupportedType(): void
    {
        foreach (['laravel', 'thinkphp'] as $framework) {
            $definition = new ResourceDefinition('Category', $framework, 'name:string,body:text,count:integer,active:boolean,on_date:date?,at_time:datetime?');
            $this->assertSame('categories', $definition->table);
            $plan = (new CrudGenerator())->plan($definition, $this->project);
            foreach ($plan as $path => $code) {
                if (str_ends_with($path, '.php')) {
                    $this->assertIsArray(token_get_all($code, TOKEN_PARSE));
                }
            }
        }
    }
}
