<?php

declare(strict_types=1);

namespace Tests\Helpers;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class FunctionsTest extends TestCase
{
    #[DataProvider('slugValues')]
    public function testSlugify(string $input, string $expected): void
    {
        self::assertSame($expected, slugify($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function slugValues(): iterable
    {
        yield 'Portuguese accents' => ['Olá, São Paulo!', 'ola-sao-paulo'];
        yield 'spaces and separators' => ['  PHP & CLI / Utils  ', 'php-cli-utils'];
        yield 'already normalized' => ['omegaalfa-utils', 'omegaalfa-utils'];
        yield 'empty value' => ['', ''];
        yield 'extended Latin' => ['Æther & Straße', 'aether-strasse'];
    }

    public function testRemoverAcentosTransliteratesAndRemovesSymbols(): void
    {
        self::assertSame('Acao util  100', removerAcentos('Ação útil — 100%'));
    }

    #[DataProvider('normalizedSpaces')]
    public function testNormalizarEspacos(string $input, string $expected): void
    {
        self::assertSame($expected, normalizarEspacos($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function normalizedSpaces(): iterable
    {
        yield 'regular spaces' => ['  Omega    Utils  ', 'Omega Utils'];
        yield 'tabs and lines' => ["PHP\tUtilities\nCLI", 'PHP Utilities CLI'];
        yield 'Unicode whitespace' => ["PHP\u{00A0}\u{2003}Utils", 'PHP Utils'];
        yield 'empty value' => ['', ''];
    }

    public function testIntegerValidationDoesNotSanitizeInvalidInput(): void
    {
        self::assertSame(42, filter_validate_int(42));
        self::assertSame(-10, filter_validate_int('-10'));
        self::assertNull(filter_validate_int('42px'));
        self::assertNull(filter_validate_int('1.5'));
    }

    public function testFloatValidationReturnsTypedValue(): void
    {
        self::assertSame(10.5, filter_validate_float('10.5'));
        self::assertSame(-2.0, filter_validate_float(-2));
        self::assertNull(filter_validate_float('10.5px'));
    }

    public function testEmailValidationReturnsNullInsteadOfMutatingInvalidAddress(): void
    {
        self::assertSame('user@example.com', filter_validate_email(' user@example.com '));
        self::assertNull(filter_validate_email('user@@example.com'));
        self::assertNull(filter_validate_email('not-an-email'));
    }

    public function testSqlHelperOnlyAcceptsSimpleIdentifiers(): void
    {
        self::assertSame('users', filter_validate_sql('users'));
        self::assertSame('_internal_id', filter_validate_sql('_internal_id'));
        self::assertNull(filter_validate_sql('users.email'));
        self::assertNull(filter_validate_sql('users; DROP TABLE users'));
        self::assertNull(filter_validate_sql('123_table'));
    }

    public function testTimestampUsesRequestedTimezoneWithoutChangingGlobalTimezone(): void
    {
        $originalTimezone = date_default_timezone_get();
        $expected = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        self::assertSame($expected->format('Y-m-d H:i'), substr(timestamp('UTC'), 0, 16));
        self::assertSame($originalTimezone, date_default_timezone_get());
    }

    public function testArrayToJsonEncodesValidData(): void
    {
        self::assertSame('{"name":"Omega","active":true}', arrayToJson([
            'name' => 'Omega',
            'active' => true,
        ]));
    }

    public function testArrayToJsonReturnsNullForInvalidDataByDefault(): void
    {
        self::assertNull(arrayToJson(['invalid' => NAN]));
    }

    public function testArrayToJsonCanExposeEncodingFailure(): void
    {
        $this->expectException(JsonException::class);
        arrayToJson(['invalid' => NAN], throw: true);
    }

    public function testJsonToArrayDecodesObjectsAndLists(): void
    {
        self::assertSame(
            ['name' => 'Omega', 'active' => true],
            jsonToArray('{"name":"Omega","active":true}')
        );
        self::assertSame([1, 2, 3], jsonToArray('[1,2,3]'));
        self::assertSame([], jsonToArray('{}'));
    }

    public function testJsonToArrayReturnsNullForMalformedJson(): void
    {
        self::assertNull(jsonToArray('{"invalid":}'));
    }

    public function testJsonToArrayCanExposeDecodingFailure(): void
    {
        $this->expectException(JsonException::class);
        jsonToArray('{"invalid":}', throw: true);
    }

    public function testJsonToArrayRejectsScalarRootValues(): void
    {
        self::assertNull(jsonToArray('null'));
        self::assertNull(jsonToArray('true'));
        self::assertNull(jsonToArray('42'));
        self::assertNull(jsonToArray('"text"'));
    }

    public function testJsonToArrayCanExposeUnexpectedRootType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('root value must be an object or array');
        jsonToArray('true', throw: true);
    }
}
