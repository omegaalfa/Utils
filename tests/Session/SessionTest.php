<?php

declare(strict_types=1);

namespace Tests\Session;

use InvalidArgumentException;
use Omegaalfa\Utils\Session\Session;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session(autoStart: false);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testStoresValuesWithoutChangingTheirTypesOrEscapingContent(): void
    {
        $values = [
            'html' => '<strong>safe at output</strong>',
            'array' => ['role' => 'admin'],
            'boolean' => true,
            'integer' => 42,
            'null' => null,
        ];

        foreach ($values as $key => $value) {
            $this->session->set($key, $value);
            self::assertSame($value, $this->session->get($key));
            self::assertTrue($this->session->has($key));
        }

        self::assertSame($values, $this->session->all());
        self::assertSame($values, $this->session->getAll());
    }

    public function testGetReturnsDefaultOnlyForMissingKeys(): void
    {
        $this->session->set('nullable', null);

        self::assertNull($this->session->get('nullable', 'fallback'));
        self::assertSame('fallback', $this->session->get('missing', 'fallback'));
        self::assertFalse($this->session->has('missing'));
    }

    public function testDeletePullAndClear(): void
    {
        $this->session->set('first', 1);
        $this->session->set('second', 2);

        self::assertSame(1, $this->session->pull('first'));
        self::assertFalse($this->session->has('first'));
        self::assertSame('fallback', $this->session->pull('missing', 'fallback'));

        $this->session->delete('second');
        self::assertSame([], $this->session->all());

        $this->session->set('value', true);
        $this->session->clear();
        self::assertSame([], $this->session->all());
    }

    public function testRejectsEmptyKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->session->set('', 'value');
    }

    public function testRegenerateRequiresActiveSession(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active PHP session');
        $this->session->regenerate();
    }

    public function testDestroyWithoutActiveSessionStillClearsData(): void
    {
        $this->session->set('key', 'value');
        $this->session->destroy();

        self::assertSame([], $this->session->all());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStartsWithSecureCookieDefaultsAndCanRegenerate(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $session = new Session(cookieOptions: ['samesite' => 'Strict']);

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        $parameters = session_get_cookie_params();
        self::assertTrue($parameters['secure']);
        self::assertTrue($parameters['httponly']);
        self::assertSame('Strict', $parameters['samesite']);

        $session->regenerate();
        $session->destroy();
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSameSiteNoneRequiresSecureCookie(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a secure cookie');

        new Session(cookieOptions: ['samesite' => 'None', 'secure' => false]);
    }
}
