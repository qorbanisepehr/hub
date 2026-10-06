<?php

namespace App\Support\Realtime\Value;

use InvalidArgumentException;

/**
 * What a run is about, as a reference the client can route on.
 *
 * A reference and never the entity itself. The client re-reads the thing by its
 * own route, so shipping a model here would couple every future run to the
 * shape of whatever it reports on - and the notification contract already
 * established the shape this mirrors ({type, id}, never a URL), which is worth
 * matching rather than inventing a second vocabulary for the same idea.
 *
 * Why it exists at all: a cache key says *what to re-read* but not *which one*.
 * A person watching a single meeting's page knows, because the URL told them.
 * A person watching a bulk run does not - ten runs share one cache prefix, so
 * without a subject every event would read as "something, somewhere, moved" and
 * the list could not show which meeting went running and which is still queued.
 */
final readonly class RunSubject
{
    /**
     * @param  string  $type  the kind of thing, in the caller's own vocabulary -
     *                        the same string the client's routes are keyed by
     * @param  string|int  $id  its identity, left as the caller gave it: an id
     *                          that arrives as a string from a route must not
     *                          silently become an integer here and then compare
     *                          unequal to the same id read from a payload
     */
    private function __construct(
        public string $type,
        public string|int $id,
    ) {}

    public static function of(string $type, string|int $id): self
    {
        if ($type === '') {
            throw new InvalidArgumentException('A run subject needs a type.');
        }

        if ($id === '' || $id === 0) {
            throw new InvalidArgumentException('A run subject needs an id.');
        }

        return new self($type, $id);
    }

    /**
     * @return array{type: string, id: string|int}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
