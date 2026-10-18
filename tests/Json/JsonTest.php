<?php

declare(strict_types=1);

namespace Tests\Json;

use JsonException;
use Omegaalfa\Utils\Json\Json;
use PHPUnit\Framework\TestCase;

final class JsonTest extends TestCase
{
    public function testEncodesValuesAndHonorsFlags(): void
    {
        self::assertSame('{"a":1}', Json::encode(['a' => 1]));
        self::assertSame("{\n    \"a\": 1\n}", Json::encode(['a' => 1], JSON_PRETTY_PRINT));
        self::assertSame('{"s3t":[1,2]}', Json::encode(['s3t' => [1, 2]]));
    }

    public function testEncodeThrowsOnInvalidStructures(): void
    {
        $this->expectException(JsonException::class);
        Json::encode(['bad' => NAN]);
    }

    public function testEncodeRejectsResources(): void
    {
        $handle = fopen('php://memory', 'r+');
        self::assertIsResource($handle);

        try {
            $this->expectException(JsonException::class);
            Json::encode(['handle' => $handle]);
        } finally {
            fclose($handle);
        }
    }

    public function testDecodesAssociativeArraysByDefault(): void
    {
        self::assertSame(['a' => 1, 'b' => ['c' => 2]], Json::decode('{"a":1,"b":{"c":2}}'));
        self::assertSame([1, 2, 3], Json::decode('[1,2,3]'));
        self::assertSame('text', Json::decode('"text"'));
    }

    public function testDecodesObjectsOnDemand(): void
    {
        $decoded = Json::decode('{"a":1}', false);

        self::assertInstanceOf(\stdClass::class, $decoded);
        self::assertSame(1, $decoded->a);
    }

    public function testDecodeThrowsOnMalformedInput(): void
    {
        $this->expectException(JsonException::class);
        Json::decode('{"a":');
    }

    public function testIsValidChecksWithoutMaterializing(): void
    {
        self::assertTrue(Json::isValid('{"a":1}'));
        self::assertTrue(Json::isValid('[]'));
        self::assertFalse(Json::isValid('{"a":'));
        self::assertFalse(Json::isValid(''));
    }
}
