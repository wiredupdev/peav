<?php

declare(strict_types=1);

/*
 * This file is part of the Peav package.
 *
 * (c) WireUpDev <wireupdev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WireUpDev\Peav\Factory;

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating default PSR-3 Monolog logger instances.
 */
final readonly class LoggerFactory
{
    /**
     * Creates a Monolog logger instance adhering to PSR-3 LoggerInterface.
     *
     * @param string $channel The logging channel name.
     * @param string|null $stream The stream resource or path (e.g. 'php://stderr', 'php://stdout'), or null for NullHandler.
     * @param Level $level Minimum log level.
     */
    public static function create(
        string $channel = 'peav',
        ?string $stream = 'php://stderr',
        Level $level = Level::Debug,
    ): LoggerInterface {
        $logger = new Logger($channel);

        if ($stream !== null && $stream !== '') {
            $logger->pushHandler(new StreamHandler($stream, $level));
        } else {
            $logger->pushHandler(new NullHandler());
        }

        return $logger;
    }
}
