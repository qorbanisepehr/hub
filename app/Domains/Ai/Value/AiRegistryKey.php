<?php

namespace App\Domains\Ai\Value;

use InvalidArgumentException;

/**
 * The key space of the `ai` settings group, in one place.
 *
 * Provider names and agent keys are both part of a stored key and both have to
 * survive a URL segment and a config key, so they are checked against the same
 * pattern. Keeping that pattern here rather than in each document is the point:
 * two documents that each carried their own copy would be two definitions of
 * what this store accepts, and a key accepted by one and refused by the other
 * is a row that can be written but never read back.
 *
 * Model names get their own, looser pattern, and the difference is not
 * arbitrary: a provider name and an agent key are identifiers this hub invents,
 * so they can be held to something tidy, while a model name is fixed by whoever
 * publishes the model and is routinely punctuated (`llama3:8b`, `qwen2.5:14b`).
 * Refusing those would make the registry unable to describe a real gateway, so
 * the colon is allowed there and nowhere else.
 *
 * Dots are allowed because the agent keys are dotted (`board-meeting.summary`)
 * and a key cannot be reshaped by the storage that holds it.
 */
final class AiRegistryKey
{
    public const PROVIDERS = 'providers/';

    public const AGENTS = 'agents/';

    public const DEFAULT_PROVIDER = 'default_provider';

    public const SEGMENT_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    public const MODEL_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/';

    /** Whether the given bare name is usable as a provider name or agent key. */
    public static function isLegalSegment(string $segment): bool
    {
        return preg_match(self::SEGMENT_PATTERN, $segment) === 1;
    }

    /** Whether the given name is usable as a model name inside a provider. */
    public static function isLegalModelName(string $model): bool
    {
        return preg_match(self::MODEL_PATTERN, $model) === 1;
    }

    /**
     * @param  string  $what  what the segment names, for the error message
     *
     * @throws InvalidArgumentException
     */
    public static function assertLegalSegment(string $segment, string $what): void
    {
        if (! self::isLegalSegment($segment)) {
            throw new InvalidArgumentException(sprintf(
                '[%s] is not a usable %s. Start with a letter or digit, then use letters, digits, dots, '
                    .'underscores or dashes — it has to survive a URL segment and an SDK provider key.',
                $segment,
                $what,
            ));
        }
    }

    /** The provider name inside `providers/{name}`, or null when the key is not one. */
    public static function providerName(string $key): ?string
    {
        return self::segmentOf($key, self::PROVIDERS);
    }

    /** The agent key inside `agents/{key}`, or null when the key is not one. */
    public static function agentKey(string $key): ?string
    {
        return self::segmentOf($key, self::AGENTS);
    }

    /**
     * @param  string  $what  what the segment names, for the error message
     *
     * @throws InvalidArgumentException
     */
    public static function assertLegalModelName(string $model, string $what = 'model name'): void
    {
        if (! self::isLegalModelName($model)) {
            throw new InvalidArgumentException(sprintf(
                '[%s] is not a usable %s. A model name is whatever the provider publishes it as, '
                    .'so colons are allowed (llama3:8b) but it still has to survive a URL segment.',
                $model,
                $what,
            ));
        }
    }

    private static function segmentOf(string $key, string $prefix): ?string
    {
        if (! str_starts_with($key, $prefix)) {
            return null;
        }

        $segment = substr($key, strlen($prefix));

        return self::isLegalSegment($segment) ? $segment : null;
    }
}
