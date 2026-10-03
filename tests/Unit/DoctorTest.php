<?php

namespace Ninex\Lib\Tests;

use Ninex\Lib\Scaffolding\{ConsoleApplication, ProjectDoctor};
use PHPUnit\Framework\TestCase;

class DoctorTest extends TestCase
{
    private string $root;
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ninex-doctor-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/vendor/composer', 0777, true);
        mkdir($this->root.'/app');
        file_put_contents($this->root.'/composer.json', '{"require":{"laravel/framework":"^13.0"}}');
        file_put_contents($this->root.'/vendor/composer/installed.json', '{"packages":[{"name":"laravel/framework","version":"v13.0.0"}]}');
    }
    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }
    public function testDoctorDetectsLegacyMigrationRisksWithoutExecutingSource(): void
    {
        file_put_contents($this->root.'/app/OldModel.php', '<?php use Ninex\Lib\Models\LibModel as Base; class OldModel extends Base {} throw new Exception("must not execute");');
        file_put_contents($this->root.'/app/OldController.php', '<?php class OldController extends \\Ninex\\Lib\\Http\\Controllers\\LibController {}');
        file_put_contents($this->root.'/app/OldService.php', '<?php class OldService extends \Ninex\Lib\Http\Services\LibService {}');
        $lines = [];
        $out = function ($line) use (&$lines) {
            $lines[] = $line;
        };
        $result = (new ConsoleApplication())->run(['doctor', '--path', $this->root, '--strict'], $out);
        $this->assertSame(2, $result);
        $this->assertStringNotContainsString('$fillable', implode("\n", $lines));
        $this->assertStringNotContainsString('validateForm', implode("\n", $lines));
        $this->assertStringNotContainsString('require a model Policy', implode("\n", $lines));
        $this->assertStringContainsString('$allowedFilters', implode("\n", $lines));
    }
    public function testFrameworkIsDetectedAndBothOptionStylesAreSupported(): void
    {
        $lines = [];
        $out = function ($line) use (&$lines) {
            $lines[] = $line;
        };
        $this->assertSame('laravel', (new ProjectDoctor())->detectFramework($this->root));
        $this->assertSame(0, (new ConsoleApplication())->run(['make:crud', 'Product', '--path', $this->root, '--fields', 'name:string', '--dry-run'], $out));
        $this->assertStringContainsString('app/Models/Product.php', implode("\n", $lines));
        $this->assertFileDoesNotExist($this->root.'/app/Models/Product.php');
        $this->assertSame(0, (new ConsoleApplication())->run(['make:crud', '--help'], $out));
        $this->assertSame(1, (new ConsoleApplication())->run(['make:crud', 'Product', '--fields', '--dry-run'], $out));
    }
    public function testDoctorReportsMissingInstalledDependencies(): void
    {
        unlink($this->root.'/vendor/composer/installed.json');
        $this->assertSame(1, (new ConsoleApplication())->run(['doctor', '--path='.$this->root], fn ($line) => null));
    }
}
