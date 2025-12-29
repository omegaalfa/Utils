<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Tests;

use Omegaalfa\Utils\Arr;
use PHPUnit\Framework\TestCase;

class ArrTest extends TestCase
{
    public function testGet(): void
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];
        
        $this->assertSame('John', Arr::get($array, 'user.name'));
        $this->assertSame(30, Arr::get($array, 'user.age'));
        $this->assertNull(Arr::get($array, 'user.email'));
        $this->assertSame('default', Arr::get($array, 'user.email', 'default'));
    }

    public function testSet(): void
    {
        $array = [];
        Arr::set($array, 'user.name', 'John');
        Arr::set($array, 'user.age', 30);
        
        $this->assertSame(['user' => ['name' => 'John', 'age' => 30]], $array);
    }

    public function testHas(): void
    {
        $array = ['user' => ['name' => 'John']];
        
        $this->assertTrue(Arr::has($array, 'user.name'));
        $this->assertFalse(Arr::has($array, 'user.email'));
    }

    public function testForget(): void
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];
        Arr::forget($array, 'user.age');
        
        $this->assertSame(['user' => ['name' => 'John']], $array);
    }

    public function testOnly(): void
    {
        $array = ['name' => 'John', 'age' => 30, 'email' => 'john@example.com'];
        $result = Arr::only($array, ['name', 'email']);
        
        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $result);
    }

    public function testExcept(): void
    {
        $array = ['name' => 'John', 'age' => 30, 'email' => 'john@example.com'];
        $result = Arr::except($array, ['age']);
        
        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $result);
    }

    public function testFirst(): void
    {
        $array = [1, 2, 3, 4, 5];
        
        $this->assertSame(1, Arr::first($array));
        $this->assertSame(3, Arr::first($array, fn($value) => $value > 2));
        $this->assertSame('default', Arr::first([], null, 'default'));
    }

    public function testLast(): void
    {
        $array = [1, 2, 3, 4, 5];
        
        $this->assertSame(5, Arr::last($array));
        $this->assertSame(4, Arr::last($array, fn($value) => $value < 5));
    }

    public function testFlatten(): void
    {
        $array = [1, [2, [3, [4]]]];
        
        $this->assertSame([1, 2, 3, 4], Arr::flatten($array));
        $this->assertSame([1, 2, [3, [4]]], Arr::flatten($array, 1));
    }

    public function testPluck(): void
    {
        $array = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];
        
        $this->assertSame(['John', 'Jane'], Arr::pluck($array, 'name'));
        $this->assertSame(['John' => 30, 'Jane' => 25], Arr::pluck($array, 'age', 'name'));
    }

    public function testWhere(): void
    {
        $array = [1, 2, 3, 4, 5];
        $result = Arr::where($array, fn($value) => $value > 2);
        
        $this->assertSame([2 => 3, 3 => 4, 4 => 5], $result);
    }

    public function testShuffle(): void
    {
        $array = [1, 2, 3, 4, 5];
        $shuffled = Arr::shuffle($array);
        
        $this->assertCount(5, $shuffled);
        $this->assertContains(1, $shuffled);
    }
}
