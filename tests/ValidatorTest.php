<?php

use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testArrayOfEnumRejectsUnknownValues(): void
    {
        $this->assertTrue(Validator::arrayOfEnum(['beginner', 'advanced'], ['beginner', 'intermediate', 'advanced']));
        $this->assertFalse(Validator::arrayOfEnum(['beginner', 'unknown'], ['beginner', 'intermediate', 'advanced']));
    }

    public function testUniqueArrayDetectsDuplicates(): void
    {
        $this->assertTrue(Validator::uniqueArray([1, 2, 3]));
        $this->assertFalse(Validator::uniqueArray([1, 2, 2]));
    }
}
