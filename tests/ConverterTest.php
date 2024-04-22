<?php

require_once __DIR__ . '/../src/Converter.php';

use PHPUnit\Framework\TestCase;
use Converter\Converter;

class ConverterTest extends TestCase
{
    public function testGitToComposerConversion()
    {
        $converter = new Converter([__DIR__ . '/patches/git/module.git.patch']);
        $result = $converter->convert();
        $expectedResult = file_get_contents(__DIR__ . '/patches/composer/module.composer.patch');
        $this->assertEquals($expectedResult, $result);
    }

    public function testComposerToGitConversion()
    {
        $converter = new Converter([__DIR__ . '/patches/composer/module.composer.patch', '-r']);
        $result = $converter->convert();
        $expectedResult = file_get_contents(__DIR__ . '/patches/git/module.git.patch');
        $this->assertEquals($expectedResult, $result);
    }
}