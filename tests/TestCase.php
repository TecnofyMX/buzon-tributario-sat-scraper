<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/Fixtures/' . $name);
        if (false === $contents) {
            self::fail(sprintf('Fixture %s could not be read.', $name));
        }

        return $contents;
    }
}
