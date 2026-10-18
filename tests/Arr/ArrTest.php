<?php

declare(strict_types=1);

namespace Tests\Arr;

use ArrayAccess;
use ArrayObject;
use InvalidArgumentException;
use Omegaalfa\Utils\Arr\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ArrTest extends TestCase
{
    /** @var array<array-key, mixed> */
    private array $sample = [
        'user' => [
            'name' => 'Ada',
            'profile' => ['city' => 'London', 'zip' => null],
        ],
        'tags' => ['php', 'utils'],
        0 => 'first',
        1 => 'second',
    ];

    #[DataProvider('getPathValues')]
    public function testGetReturnsValueOrDefault(string|int $key, mixed $expected): void
    {
        self::assertSame($expected, Arr::get($this->sample, $key, 'missing'));
    }

    /** @return iterable<string, array{string|int, mixed}> */
    public static function getPathValues(): iterable
    {
        yield 'top-level' => ['user', ['name' => 'Ada', 'profile' => ['city' => 'London', 'zip' => null]]];
        yield 'nested' => ['user.name', 'Ada'];
        yield 'explicit null' => ['user.profile.zip', null];
        yield 'missing nested' => ['user.profile.country', 'missing'];
        yield 'missing top-level' => ['absent', 'missing'];
        yield 'integer key' => [0, 'first'];
        yield 'list offset' => ['tags.1', 'utils'];
    }

    public function testHasDistinguishesMissingFromNull(): void
    {
        self::assertTrue(Arr::has($this->sample, 'user.name'));
        self::assertTrue(Arr::has($this->sample, 'user.profile.zip'));
        self::assertFalse(Arr::has($this->sample, 'user.profile.country'));
        self::assertFalse(Arr::has($this->sample, 'absent'));
    }

    public function testSetReturnsNewArrayAndCreatesNestedPaths(): void
    {
        $original = ['a' => ['b' => 1]];
        $modified = Arr::set($original, 'a.c.d', 2);

        self::assertSame(['a' => ['b' => 1]], $original);
        self::assertSame(['a' => ['b' => 1, 'c' => ['d' => 2]]], $modified);

        self::assertSame(['x' => ['y' => 'z']], Arr::set([], 'x.y', 'z'));
        self::assertSame(['a' => 2], Arr::set(['a' => 1], 'a', 2));
        self::assertSame(['a' => ['b' => 'overwritten']], Arr::set(['a' => 'scalar'], 'a.b', 'overwritten'));
    }

    public function testForgetRemovesPathsAndIgnoresMissingOnes(): void
    {
        $result = Arr::forget($this->sample, 'user.profile.city', 'absent', 'tags');

        self::assertFalse(Arr::has($result, 'user.profile.city'));
        self::assertFalse(Arr::has($result, 'tags'));
        self::assertSame('Ada', Arr::get($result, 'user.name'));
        self::assertNotSame($this->sample, $result);
    }

    public function testForgetStopsAtNonArrayIntermediates(): void
    {
        self::assertSame(['a' => 'scalar'], Arr::forget(['a' => 'scalar'], 'a.b'));
    }

    public function testPluckExtractsValuesPerElement(): void
    {
        $rows = [
            ['id' => 1, 'meta' => ['role' => 'admin']],
            ['id' => 2, 'meta' => ['role' => 'user']],
            ['id' => 3],
        ];

        self::assertSame([1, 2, 3], Arr::pluck($rows, 'id'));
        self::assertSame(['admin', 'user', 'none'], Arr::pluck($rows, 'meta.role', 'none'));
    }

    public function testPluckReadsPublicObjectProperties(): void
    {
        $first = new stdClass();
        $first->user = (object) ['name' => 'Ada'];
        $second = new stdClass();
        $second->user = (object) ['name' => 'Grace'];

        self::assertSame(['Ada', 'Grace'], Arr::pluck([$first, $second], 'user.name'));
    }

    public function testGroupByPathAndCallback(): void
    {
        $rows = [
            ['type' => 'a', 'n' => 1],
            ['type' => 'b', 'n' => 2],
            ['type' => 'a', 'n' => 3],
        ];

        self::assertSame(
            [
                'a' => [['type' => 'a', 'n' => 1], ['type' => 'a', 'n' => 3]],
                'b' => [['type' => 'b', 'n' => 2]],
            ],
            Arr::groupBy($rows, 'type'),
        );

        self::assertSame(
            ['odd' => [1, 3], 'even' => [2]],
            Arr::groupBy([1, 2, 3], static fn (mixed $n): string => (is_int($n) && $n % 2 === 0) ? 'even' : 'odd'),
        );
    }

    public function testGroupByRejectsMissingPathAndInvalidCallbackKey(): void
    {
        try {
            Arr::groupBy([['n' => 1]], 'missing');
            self::fail('A missing group path must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        Arr::groupBy([1], static fn (mixed $n): array => [$n]);
    }

    public function testOnlyKeepsRequestedKeys(): void
    {
        self::assertSame(
            ['user' => ['name' => 'Ada'], 'tags' => ['php', 'utils']],
            Arr::only($this->sample, ['user.name', 'tags', 'absent']),
        );
        self::assertSame([], Arr::only($this->sample, ['absent']));
    }

    public function testDotAndUndotRoundTripNestedArrays(): void
    {
        $nested = ['a' => ['b' => ['c' => 1], 'd' => 2], 'e' => []];

        self::assertSame(['a.b.c' => 1, 'a.d' => 2, 'e' => []], Arr::dot($nested));
        self::assertSame($nested, Arr::undot(['a.b.c' => 1, 'a.d' => 2, 'e' => []]));
    }

    public function testFirstAndLastReturnMatchesOrDefault(): void
    {
        $values = [1, 2, 3, 4];

        self::assertSame(1, Arr::first($values));
        self::assertSame(4, Arr::last($values));
        self::assertSame(3, Arr::first($values, static fn (mixed $n): bool => is_int($n) && $n > 2));
        self::assertSame(4, Arr::last($values, static fn (mixed $n): bool => is_int($n) && $n > 2));
        self::assertSame('none', Arr::first($values, static fn (mixed $n): bool => is_int($n) && $n > 9, 'none'));
        self::assertSame('none', Arr::last($values, static fn (mixed $n): bool => is_int($n) && $n > 9, 'none'));
        self::assertSame('none', Arr::first([], default: 'none'));
        self::assertSame('none', Arr::last([], default: 'none'));
    }

    public function testFirstAndLastKeepNullMatches(): void
    {
        $values = ['a' => null, 'b' => null];

        self::assertNull(Arr::first($values));
        self::assertNull(Arr::last($values));
        self::assertNull(Arr::first($values, static fn ($value): bool => true, 'none'));
    }

    public function testReadsArrayAccessAndPublicObjectProperties(): void
    {
        $access = new ArrayObject(['user' => ['name' => 'Ada']]);
        self::assertSame('Ada', Arr::get(['wrapper' => $access], 'wrapper.user.name'));

        $object = new stdClass();
        $object->user = (object) ['name' => 'Grace', 'hidden' => null];
        self::assertSame('Grace', Arr::get(['wrapper' => $object], 'wrapper.user.name'));
        self::assertTrue(Arr::has(['wrapper' => $object], 'wrapper.user.hidden'));
        self::assertFalse(Arr::has(['wrapper' => $object], 'wrapper.user.absent'));
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsInvalidKeyPaths(string|int $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        Arr::get([], $key);
    }

    /** @return iterable<string, array{string|int}> */
    public static function invalidPaths(): iterable
    {
        yield 'empty string' => [''];
        yield 'empty segment' => ['a..b'];
        yield 'trailing dot' => ['a.'];
        yield 'NUL byte' => ["a\0b"];
    }

    public function testArrayAccessOffsetExistsUsesIssetSemantics(): void
    {
        $access = new class implements ArrayAccess {
            public function offsetExists(mixed $offset): bool
            {
                return isset($this->storage[$this->key($offset)]);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return $this->storage[$this->key($offset)] ?? null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                $this->storage[$this->key($offset)] = $value;
            }

            public function offsetUnset(mixed $offset): void
            {
                unset($this->storage[$this->key($offset)]);
            }

            private function key(mixed $offset): string
            {
                if (is_string($offset)) {
                    return $offset;
                }

                return is_int($offset) ? (string) $offset : '';
            }

            /** @var array<string, mixed> */
            private array $storage = ['nullable' => null, 'value' => 'ok'];
        };

        self::assertSame('ok', Arr::get(['root' => $access], 'root.value'));
        self::assertFalse(Arr::has(['root' => $access], 'root.nullable'));
        self::assertFalse(Arr::has(['root' => $access], 'root.missing'));
    }
}
