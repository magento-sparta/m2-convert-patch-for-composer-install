#!/usr/bin/env php
<?php

require_once __DIR__ . '/src/Converter.php';

use Converter\Converter;

function showHelp()
{
    echo <<<HELP_TEXT
Usage: php -f convert-for-composer.php [options] file [> new-file]
 convert-for-composer.php [options] file [> new-file]

file        path to source PATCH file

[options]
-h, --help  Show help
-r          Reverse mode. Convert composer format back to git

HELP_TEXT;
    exit(0);
}

function parseArgs($args)
{
    $filepath = null;
    $r = false;

    if (count($args) < 1) {
        showHelp();
    }

    foreach ($args as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            showHelp();
        } elseif ($arg === '-r') {
            $r = true;
        } else {
            $filepath = $arg;
        }
    }

    return [$filepath, $r];
}

// Remove the first argument (the script name)
$args = array_slice($argv, 1);
$params = parseArgs($args);

$converter = new Converter($params);
$result = $converter->convert();
echo $result;
exit(0);
