<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use Omegaalfa\Utils\Str\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    public function testNativeContainmentOperations(): void
    {
        self::assertTrue(Str::contains('omegaalfa/utils', 'utils'));
        self::assertFalse(Str::contains('omegaalfa/utils', 'framework'));
        self::assertTrue(Str::contains('value', ''));
        self::assertTrue(Str::startsWith('omegaalfa', 'omega'));
        self::assertFalse(Str::startsWith('omegaalfa', 'alfa'));
        self::assertTrue(Str::endsWith('omegaalfa', 'alfa'));
        self::assertFalse(Str::endsWith('omegaalfa', 'omega'));
    }

    #[DataProvider('slugValues')]
    public function testSlugify(string $text, string $divider, string $expected): void
    {
        self::assertSame($expected, Str::slugify($text, $divider));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function slugValues(): iterable
    {
        yield 'Portuguese' => ['Olá, São Paulo!', '-', 'ola-sao-paulo'];
        yield 'Latin accents' => ['Crème brûlée', '_', 'creme_brulee'];
        yield 'untranslatable scripts are dropped' => ['Crème brûlée — 東京', '_', 'creme_brulee'];
        yield 'ligatures' => ['ﬁle ﬂow', '-', 'file-flow'];
        yield 'Turkish dotted I' => ['İstanbul', '-', 'istanbul'];
        yield 'separators' => ['  PHP && Utils  ', '-', 'php-utils'];
        yield 'empty' => ['', '-', ''];
    }

    #[DataProvider('invalidDividers')]
    public function testSlugifyRejectsUnsafeDivider(string $divider): void
    {
        $this->expectException(InvalidArgumentException::class);
        Str::slugify('value', $divider);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDividers(): iterable
    {
        yield 'empty' => [''];
        yield 'multiple bytes' => ['--'];
        yield 'letter' => ['a'];
        yield 'number' => ['1'];
    }

    public function testTruncatePreservesMultibyteCharacters(): void
    {
        self::assertSame('Olá 👋', Str::truncate('Olá 👋', 5));
        self::assertSame('Olá...', Str::truncate('Olá mundo', 3));
        self::assertSame('😀🚀…', Str::truncate('😀🚀✨', 2, '…'));
        self::assertSame('...', Str::truncate('text', 0));
    }

    public function testTruncateRejectsNegativeLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Str::truncate('text', -1);
    }

    #[DataProvider('randomLengths')]
    public function testRandomReturnsExactHexadecimalLength(int $length): void
    {
        $value = Str::random($length);
        self::assertSame($length, strlen($value));
        self::assertMatchesRegularExpression('/^[a-f0-9]*$/', $value);
    }

    /** @return iterable<string, array{int}> */
    public static function randomLengths(): iterable
    {
        yield 'empty' => [0];
        yield 'odd' => [15];
        yield 'even' => [16];
        yield 'single' => [1];
    }

    public function testRandomProducesDifferentValues(): void
    {
        self::assertNotSame(Str::random(32), Str::random(32));
    }

    public function testRandomRejectsNegativeLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Str::random(-1);
    }

    #[DataProvider('maskValues')]
    public function testMask(
        string $value,
        string $character,
        int $index,
        ?int $length,
        string $expected,
    ): void {
        self::assertSame($expected, Str::mask($value, $character, $index, $length));
    }

    /** @return iterable<string, array{string, string, int, int|null, string}> */
    public static function maskValues(): iterable
    {
        yield 'card middle' => ['4111111111111111', '*', 6, 6, '411111******1111'];
        yield 'to end' => ['secret-token', '*', 7, null, 'secret-*****'];
        yield 'negative index' => ['user@example.com', '*', -3, 2, 'user@example.**m'];
        yield 'length capped' => ['abc', '*', 1, 99, 'a**'];
        yield 'index beyond end' => ['abc', '*', 99, null, 'abc'];
        yield 'zero length' => ['abc', '*', 1, 0, 'abc'];
    }

    public function testMaskRejectsInvalidCharacter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Str::mask('secret', '**', 0);
    }

    public function testMaskRejectsNegativeLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Str::mask('secret', '*', 0, -1);
    }

    #[DataProvider('maskUtf8Values')]
    public function testMaskUtf8(
        string $value,
        string $character,
        int $index,
        ?int $length,
        string $expected,
    ): void {
        self::assertSame($expected, Str::maskUtf8($value, $character, $index, $length));
    }

    /** @return iterable<string, array{string, string, int, int|null, string}> */
    public static function maskUtf8Values(): iterable
    {
        yield 'multibyte kept intact' => ['Olá mundo', '*', 0, 3, '*** mundo'];
        yield 'middle accents' => ['ação', '*', 1, 2, 'a**o'];
        yield 'emoji units' => ['😀🚀✨', '*', 1, 1, '😀*✨'];
        yield 'to the end' => ['secreto', '*', 3, null, 'sec****'];
        yield 'negative index' => ['user@exemplo.com', '*', -4, 2, 'user@exemplo**om'];
        yield 'length capped' => ['Olá', '*', 1, 99, 'O**'];
        yield 'index beyond end' => ['Olá', '*', 99, null, 'Olá'];
        yield 'zero length' => ['Olá', '*', 1, 0, 'Olá'];
        yield 'multibyte mask character' => ['abcd', '•', 1, 2, 'a••d'];
    }

    public function testMaskUtf8RejectsInvalidInput(): void
    {
        try {
            Str::maskUtf8("ol\301a", '*', 0);
            self::fail('Invalid UTF-8 input must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        try {
            Str::maskUtf8('abc', '**', 0);
            self::fail('A multi-character mask must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        Str::maskUtf8('abc', '*', 0, -1);
    }
}
