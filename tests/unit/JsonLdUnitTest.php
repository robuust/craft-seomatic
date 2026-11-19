<?php

namespace nystudio107\seomatictests\unit;

use Codeception\Test\Unit;
use nystudio107\seomatic\models\MetaJsonLd;

/**
 * JSON-LD rendering resolves metadata before emitting Schema.org objects.
 */
class JsonLdUnitTest extends Unit
{
    public function testJsonLdRendersTemplateValuesAndSchemaKeys(): void
    {
        $model = MetaJsonLd::create('Organization', [
            'name' => '{{ "Robuust" }}',
            'url' => 'https://example.test/',
            'logo' => [
                'type' => 'ImageObject',
                'url' => 'https://example.test/logo.jpg',
                'width' => 512,
                'height' => 512,
            ],
        ]);

        $rendered = $model->render([
            'renderRaw' => true,
            'renderScriptTags' => false,
            'array' => true,
        ]);
        $data = json_decode($rendered, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Organization', $data['@type']);
        $this->assertSame('Robuust', $data['name']);
        $this->assertSame('https://example.test', $data['url']);
        $this->assertSame('ImageObject', $data['logo']['@type']);
        $this->assertSame('https://example.test/logo.jpg', $data['logo']['url']);
        $this->assertArrayNotHasKey('type', $data);
        $this->assertArrayNotHasKey('include', $data);
    }
}
