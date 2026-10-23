<?php

namespace Botble\WordpressImporter\Exceptions;

use RuntimeException;

/**
 * Raised when a remote image cannot be fetched or stored.
 *
 * `retryable` separates transient failures (timeouts, 5xx, rate limits) that a
 * queued job should retry from permanent ones (bad MIME, host blocked, WAF
 * challenge) where retrying only burns the worker's time. `blocksHost` marks
 * failures that will hit every URL on the same host (unreachable, WAF
 * challenge), so the rest of the run can skip that host instead of waiting on
 * a timeout per image.
 */
class ImageDownloadException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly bool $blocksHost = false,
    ) {
        parent::__construct($message);
    }

    public static function permanent(string $message, bool $blocksHost = false): self
    {
        return new self($message, false, $blocksHost);
    }

    public static function transient(string $message, bool $blocksHost = false): self
    {
        return new self($message, true, $blocksHost);
    }
}
