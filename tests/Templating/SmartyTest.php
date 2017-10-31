<?php

namespace Tests\Templating;

use AetherConfig;
use AetherServiceLocator;
use PHPUnit\Framework\TestCase;

class SmartyTest extends TestCase
{
    public function testGetSmartyEngine()
    {
        $tpl = $this->getTemplateEngine(['foo' => [
            'a' => 'hello',
            'b' => 'world',
        ]]);

        $this->assertContains('hello world', $tpl->fetch('test.tpl'));
    }

    public function testTemplateExists()
    {
        $tpl = $this->getTemplateEngine();

        $this->assertTrue($tpl->templateExists('test.tpl'));
        $this->assertFalse($tpl->templateExists('martin.tpl'));
    }

    protected function tearDown()
    {
        array_map('unlink', glob(__DIR__ . '/templates/compiled/*.php'));
    }

    private function getTemplateEngine(array $data = [])
    {
        $sl = new AetherServiceLocator;
        $sl->set('projectRoot', __DIR__ . '/');

        $config = new AetherConfig('./aether.config.xml');
        $sl->set('aetherConfig', $config);

        $tpl = $sl->getTemplate();

        foreach ($data as $key => $value) {
            $tpl->set($key, $value);
        }

        return $tpl;
    }
}
