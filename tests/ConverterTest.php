<?php

require_once __DIR__ . '/../src/Converter.php';

use PHPUnit\Framework\TestCase;
use Converter\Converter;

class ConverterTest extends TestCase
{
    private static string $patchDir = __DIR__ . "/patches/";

    /**
     * @dataProvider patchProvider
     */
    public function testGitToComposerConversion($gitPatch, $composerPatch)
    {
        print ("\n\033[33m" . "GIT > COMPOSER conversion for " . basename($gitPatch) . "\n\033[0m");
        $converter = new Converter([self::$patchDir . $gitPatch]);
        $result = $converter->convert();
        $expectedResult = file_get_contents(self::$patchDir . $composerPatch);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider patchProvider
     */
    public function testComposerToGitConversion($gitPatch, $composerPatch)
    {
        print ("\n\033[33m" . "COMPOSER > GIT conversion for " . basename($composerPatch) . "\n\033[0m");
        $converter = new Converter([self::$patchDir . $composerPatch, '-r']);
        $result = $converter->convert();
        $expectedResult = file_get_contents(self::$patchDir . $gitPatch);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for test methods.
     *
     * For each pair of A.git.patch and A.composer.patch file in the /patches directory,
     * it adds a pair of [gitPatch, composerPatch] to the data provider array.
     * 
     *
     * @return array The array of pairs of file paths for git and composer patch files.
     */
    public static function patchProvider()
    {
        $patches = [];
        $gitPatches = glob(__DIR__ . '/patches/*.git.patch');
        foreach ($gitPatches as $gitPatch) {
            $basename = basename($gitPatch, '.git.patch');
            $composerPatchFileName = "$basename.composer.patch";
            if (file_exists(self::$patchDir . $composerPatchFileName)) {
                $gitPatchFileName = "$basename.git.patch";
                $patches[] = [$gitPatchFileName, $composerPatchFileName];
            }
        }
        return $patches;
    }
}
