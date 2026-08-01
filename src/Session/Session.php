<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Session;

use InvalidArgumentException;
use RuntimeException;

final class Session
{
    /**
     * @var Session|null
     */
    private static ?self $instance = null;

    /** @param array<string, mixed> $cookieOptions */
    public function __construct(bool $autoStart = true, array $cookieOptions = [])
    {
        if ($autoStart) {
            $this->start($cookieOptions);
        }
    }

    /**
     * @return self
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** @param array<string, mixed> $cookieOptions */
    public function start(array $cookieOptions = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException('PHP sessions are disabled.');
        }
        if (headers_sent($file, $line)) {
            throw new RuntimeException("Cannot start session after headers were sent at {$file}:{$line}.");
        }
        session_set_cookie_params($this->cookieOptions($cookieOptions));
        if (!session_start()) {
            throw new RuntimeException('Unable to start PHP session.');
        }
    }

    /**
     * @param bool $deleteOldSession
     * @return void
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->requireActive();
        if (!session_regenerate_id($deleteOldSession)) {
            throw new RuntimeException('Unable to regenerate the session ID.');
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->assertKey($key);
        $_SESSION[$key] = $value;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertKey($key);
        return array_key_exists($key, $_SESSION ?? []) ? $_SESSION[$key] : $default;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->assertKey($key);
        return array_key_exists($key, $_SESSION ?? []);
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        $this->assertKey($key);
        unset($_SESSION[$key]);
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /** @return array<array-key, mixed> */
    public function getAll(): array
    {
        return $_SESSION ?? [];
    }

    /** @return array<array-key, mixed> */
    public function all(): array
    {
        return $this->getAll();
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->clear();
            return;
        }
        $this->clear();
        if (!session_destroy()) {
            throw new RuntimeException('Unable to destroy PHP session.');
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{lifetime: int, path: string, domain: string, secure: bool,
     *     httponly: bool, samesite: 'Lax'|'Strict'|'None'}
     */
    private function cookieOptions(array $overrides): array
    {
        $https = $_SERVER['HTTPS'] ?? null;
        $secure = is_string($https) && $https !== '' && strtolower($https) !== 'off';
        $options = array_replace([
            'lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => $secure,
            'httponly' => true, 'samesite' => 'Lax',
        ], $overrides);

        $sameSite = $options['samesite'];
        if (!is_string($sameSite) || !in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new InvalidArgumentException('SameSite must be Lax, Strict, or None.');
        }
        $cookieSecure = $options['secure'];
        if (!is_bool($cookieSecure)) {
            throw new InvalidArgumentException('Cookie option secure must be a boolean.');
        }
        if ($sameSite === 'None' && !$cookieSecure) {
            throw new InvalidArgumentException('SameSite=None requires a secure cookie.');
        }
        $lifetime = $options['lifetime'];
        if (!is_int($lifetime) || $lifetime < 0) {
            throw new InvalidArgumentException('Cookie lifetime must be a non-negative integer.');
        }
        $path = $options['path'];
        $domain = $options['domain'];
        if (!is_string($path) || !is_string($domain)) {
            throw new InvalidArgumentException('Cookie path and domain must be strings.');
        }
        $httpOnly = $options['httponly'];
        if (!is_bool($httpOnly)) {
            throw new InvalidArgumentException('Cookie option httponly must be a boolean.');
        }

        return [
            'lifetime' => $lifetime, 'path' => $path, 'domain' => $domain,
            'secure' => $cookieSecure, 'httponly' => $httpOnly, 'samesite' => $sameSite,
        ];
    }

    /**
     * @return void
     */
    private function requireActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('No active PHP session.');
        }
    }

    /**
     * @param string $key
     * @return void
     */
    private function assertKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Session key cannot be empty.');
        }
    }
}
