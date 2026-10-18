<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Process;

use RuntimeException;

/**
 * Thrown when a process exceeds its execution budget and is killed.
 */
final class ProcessTimedOutException extends RuntimeException
{
}
