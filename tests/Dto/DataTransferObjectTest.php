<?php

declare(strict_types=1);

namespace Tests\Dto;

use Omegaalfa\Utils\Dto\DataTransferObject;
use PHPUnit\Framework\TestCase;
use TypeError;

final readonly class CreateUserData extends DataTransferObject
{
    public function __construct(
        public string $name,
        public string $email,
        public int $age,
    ) {
    }
}

final class DataTransferObjectTest extends TestCase
{
    public function testCreatesTypedDtoNormally(): void
    {
        $data = new CreateUserData(
            name: 'Wesley',
            email: 'wesley@example.com',
            age: 45,
        );

        self::assertSame('Wesley', $data->name);
        self::assertSame('wesley@example.com', $data->email);
        self::assertSame(45, $data->age);
    }

    public function testCreatesDtoFromAssociativeArray(): void
    {
        $data = CreateUserData::fromArray([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'age' => 36,
        ]);

        self::assertInstanceOf(CreateUserData::class, $data);
        self::assertSame('Ada', $data->name);
    }

    public function testConvertsToArrayAndJson(): void
    {
        $data = new CreateUserData('Wesley', 'wesley@example.com', 45);
        $expected = [
            'name' => 'Wesley',
            'email' => 'wesley@example.com',
            'age' => 45,
        ];

        self::assertSame($expected, $data->toArray());
        self::assertSame($expected, $data->jsonSerialize());
        self::assertSame(
            '{"name":"Wesley","email":"wesley@example.com","age":45}',
            json_encode($data, JSON_THROW_ON_ERROR),
        );
    }

    public function testWithCreatesModifiedCopyWithoutChangingOriginal(): void
    {
        $original = new CreateUserData('Wesley', 'wesley@example.com', 45);
        $modified = $original->with(['age' => 46]);

        self::assertNotSame($original, $modified);
        self::assertSame(45, $original->age);
        self::assertSame(46, $modified->age);
        self::assertSame($original->name, $modified->name);
    }

    public function testNativeConstructorEnforcesTypes(): void
    {
        $this->expectException(TypeError::class);

        $_ = CreateUserData::fromArray([
            'name' => 'Wesley',
            'email' => 'wesley@example.com',
            'age' => 'invalid',
        ]);
    }

    public function testNativeConstructorRejectsUnknownFields(): void
    {
        $this->expectException(\Error::class);

        $_ = CreateUserData::fromArray([
            'name' => 'Wesley',
            'email' => 'wesley@example.com',
            'age' => 45,
            'unknown' => true,
        ]);
    }
}
