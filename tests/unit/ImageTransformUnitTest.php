<?php

namespace nystudio107\seomatictests\unit;

use Codeception\Test\Unit;
use CraftCms\Cms\Asset\Data\AssetTransformResult;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\ElementCollection;
use DateTimeImmutable;
use nystudio107\seomatic\helpers\ImageTransform;

/**
 * Native Craft assets retain their social image URL and dimensions.
 */
class ImageTransformUnitTest extends Unit
{
    public function testNativeAssetsAndCollectionsProvideSocialTransforms(): void
    {
        $asset = $this->createMock(Asset::class);
        $asset->dateModified = new DateTimeImmutable('2026-11-19 00:00:00 UTC');
        $asset->dateUpdated = $asset->dateModified;
        $asset->method('getMimeType')->willReturn('image/jpeg');
        $asset->method('transform')->willReturn(new AssetTransformResult(
            url: 'https://example.test/social.jpg',
            mimeType: 'image/jpeg',
            width: 1200,
            height: 630,
        ));

        foreach ([$asset, [$asset], new ElementCollection([$asset])] as $input) {
            $this->assertSame(
                'https://example.test/social.jpg?mtime='.$asset->dateModified->getTimestamp(),
                ImageTransform::socialTransform($input, 'base', 1, 'crop'),
            );
            $this->assertSame('1200', ImageTransform::socialTransformWidth($input, 'base', 1, 'crop'));
            $this->assertSame('630', ImageTransform::socialTransformHeight($input, 'base', 1, 'crop'));
        }
    }
}
