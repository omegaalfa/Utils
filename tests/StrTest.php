<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Tests;

use Omegaalfa\Utils\Str;
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase
{
    public function testContains(): void
    {
        $this->assertTrue(Str::contains('Hello World', 'World'));
        $this->assertFalse(Str::contains('Hello World', 'world'));
    }

    public function testStartsWith(): void
    {
        $this->assertTrue(Str::startsWith('Hello World', 'Hello'));
        $this->assertFalse(Str::startsWith('Hello World', 'World'));
    }

    public function testEndsWith(): void
    {
        $this->assertTrue(Str::endsWith('Hello World', 'World'));
        $this->assertFalse(Str::endsWith('Hello World', 'Hello'));
    }

    public function testCamelCase(): void
    {
        $this->assertSame('helloWorld', Str::camelCase('hello_world'));
        $this->assertSame('helloWorld', Str::camelCase('hello-world'));
    }

    public function testStudlyCase(): void
    {
        $this->assertSame('HelloWorld', Str::studlyCase('hello_world'));
        $this->assertSame('HelloWorld', Str::studlyCase('hello-world'));
    }

    public function testSnakeCase(): void
    {
        $this->assertSame('hello_world', Str::snakeCase('helloWorld'));
        $this->assertSame('hello_world', Str::snakeCase('HelloWorld'));
    }

    public function testKebabCase(): void
    {
        $this->assertSame('hello-world', Str::kebabCase('helloWorld'));
        $this->assertSame('hello-world', Str::kebabCase('HelloWorld'));
    }

    public function testLimit(): void
    {
        $this->assertSame('Hello...', Str::limit('Hello World', 5));
        $this->assertSame('Hello World', Str::limit('Hello World', 20));
    }

    public function testRandom(): void
    {
        $random1 = Str::random(16);
        $random2 = Str::random(16);
        $this->assertSame(16, strlen($random1));
        $this->assertNotSame($random1, $random2);
    }

    public function testSlug(): void
    {
        $this->assertSame('hello-world', Str::slug('Hello World'));
        $this->assertSame('hello_world', Str::slug('Hello World', '_'));
    }

    public function testUpper(): void
    {
        $this->assertSame('HELLO WORLD', Str::upper('hello world'));
    }

    public function testLower(): void
    {
        $this->assertSame('hello world', Str::lower('HELLO WORLD'));
    }

    public function testTitle(): void
    {
        $this->assertSame('Hello World', Str::title('hello world'));
    }

    public function testReplaceFirst(): void
    {
        $this->assertSame('Hi World World', Str::replaceFirst('Hello', 'Hi', 'Hello World World'));
    }

    public function testReplaceLast(): void
    {
        $this->assertSame('Hello World Hi', Str::replaceLast('Hello', 'Hi', 'Hello World Hello'));
    }

    public function testBefore(): void
    {
        $this->assertSame('Hello', Str::before('Hello World', ' World'));
        $this->assertSame('Hello World', Str::before('Hello World', 'Missing'));
    }

    public function testAfter(): void
    {
        $this->assertSame(' World', Str::after('Hello World', 'Hello'));
        $this->assertSame('Hello World', Str::after('Hello World', 'Missing'));
    }
}
