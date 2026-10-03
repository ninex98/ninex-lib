<?php

namespace Ninex\Lib\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/tools/release-metadata.php';

class ReleaseMetadataTest extends TestCase
{
    public function testStableAndPrereleaseTags(): void
    {
        $this->assertSame(['tag' => 'v2.0.0', 'version' => '2.0.0', 'prerelease' => false], ninexReleaseMetadata('v2.0.0'));
        foreach (['v2.0.0-rc.1', '2.0.0-RC1', 'v2.0.0-beta2', '2.0.0-alpha.1'] as $tag) {
            $this->assertTrue(ninexReleaseMetadata($tag)['prerelease']);
        }
        $this->assertFalse(ninexReleaseMetadata('1.0.9')['prerelease']);
    }
    public function testBranchNamesAndMalformedTagsAreRejected(): void
    {
        foreach (['main', 'v2.0', 'v02.0.0', 'v2.0.0; echo bad', 'v2.0.0\n', '../v2.0.0', 'v2.0.0-unknown'] as $tag) {
            try {
                ninexReleaseMetadata($tag);
                $this->fail('Accepted malformed tag');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }
}
