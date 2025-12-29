<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Tests;

use Omegaalfa\Utils\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testEmail(): void
    {
        $this->assertTrue(Validator::email('test@example.com'));
        $this->assertFalse(Validator::email('invalid-email'));
    }

    public function testUrl(): void
    {
        $this->assertTrue(Validator::url('https://example.com'));
        $this->assertTrue(Validator::url('http://example.com'));
        $this->assertFalse(Validator::url('not-a-url'));
    }

    public function testIp(): void
    {
        $this->assertTrue(Validator::ip('192.168.1.1'));
        $this->assertTrue(Validator::ip('2001:0db8:85a3:0000:0000:8a2e:0370:7334'));
        $this->assertFalse(Validator::ip('invalid-ip'));
    }

    public function testIpv4(): void
    {
        $this->assertTrue(Validator::ipv4('192.168.1.1'));
        $this->assertFalse(Validator::ipv4('2001:0db8:85a3:0000:0000:8a2e:0370:7334'));
    }

    public function testIpv6(): void
    {
        $this->assertTrue(Validator::ipv6('2001:0db8:85a3:0000:0000:8a2e:0370:7334'));
        $this->assertFalse(Validator::ipv6('192.168.1.1'));
    }

    public function testNumeric(): void
    {
        $this->assertTrue(Validator::numeric(123));
        $this->assertTrue(Validator::numeric('123'));
        $this->assertTrue(Validator::numeric(12.34));
        $this->assertFalse(Validator::numeric('abc'));
    }

    public function testAlpha(): void
    {
        $this->assertTrue(Validator::alpha('abc'));
        $this->assertTrue(Validator::alpha('ABC'));
        $this->assertFalse(Validator::alpha('abc123'));
    }

    public function testAlphaNumeric(): void
    {
        $this->assertTrue(Validator::alphaNumeric('abc123'));
        $this->assertFalse(Validator::alphaNumeric('abc-123'));
    }

    public function testAlphaDash(): void
    {
        $this->assertTrue(Validator::alphaDash('abc-123_def'));
        $this->assertFalse(Validator::alphaDash('abc 123'));
    }

    public function testLength(): void
    {
        $this->assertTrue(Validator::length('hello', 3, 10));
        $this->assertFalse(Validator::length('hi', 3, 10));
        $this->assertTrue(Validator::length('hello', 5));
    }

    public function testMin(): void
    {
        $this->assertTrue(Validator::min(10, 5));
        $this->assertTrue(Validator::min('hello', 3));
        $this->assertTrue(Validator::min([1, 2, 3], 2));
        $this->assertFalse(Validator::min(3, 5));
    }

    public function testMax(): void
    {
        $this->assertTrue(Validator::max(5, 10));
        $this->assertTrue(Validator::max('hello', 10));
        $this->assertTrue(Validator::max([1, 2], 5));
        $this->assertFalse(Validator::max(15, 10));
    }

    public function testBetween(): void
    {
        $this->assertTrue(Validator::between(5, 1, 10));
        $this->assertFalse(Validator::between(15, 1, 10));
    }

    public function testIn(): void
    {
        $this->assertTrue(Validator::in('apple', ['apple', 'banana', 'orange']));
        $this->assertFalse(Validator::in('grape', ['apple', 'banana', 'orange']));
    }

    public function testNotIn(): void
    {
        $this->assertTrue(Validator::notIn('grape', ['apple', 'banana', 'orange']));
        $this->assertFalse(Validator::notIn('apple', ['apple', 'banana', 'orange']));
    }

    public function testRegex(): void
    {
        $this->assertTrue(Validator::regex('abc123', '/^[a-z0-9]+$/'));
        $this->assertFalse(Validator::regex('abc-123', '/^[a-z0-9]+$/'));
    }

    public function testJson(): void
    {
        $this->assertTrue(Validator::json('{"name":"John"}'));
        $this->assertTrue(Validator::json('["apple","banana"]'));
        $this->assertFalse(Validator::json('invalid-json'));
    }

    public function testDate(): void
    {
        $this->assertTrue(Validator::date('2024-01-01'));
        $this->assertTrue(Validator::date('01/01/2024', 'm/d/Y'));
        $this->assertFalse(Validator::date('invalid-date'));
    }
}
