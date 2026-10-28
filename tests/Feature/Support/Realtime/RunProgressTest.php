<?php

use App\Support\Realtime\Concerns\ReportsRunProgress;
use App\Support\Realtime\RunProgressed;
use App\Support\Realtime\RunState;
use App\Support\Realtime\Value\RunSubject;
use Illuminate\Support\Facades\Event;

/*
 * The realtime run-progress kernel (`App\Support\Realtime`).
 *
 * These tests are about the *contract* the kernel promises, because that is the
 * part every future run depends on:
 *
 *   1. an event is a nudge, not a source of truth - it names what to re-read;
 *   2. progress only ever reaches the person who asked for the run;
 *   3. a run with nobody listening is silent and still succeeds;
 *   4. a broken broadcast cannot fail the work.
 */

/** A stand-in job: the kernel's trait has to work on any job, not a real one. */
final class FakeProgressJob
{
    use ReportsRunProgress;

    public function report(RunState $state, array $context = []): void
    {
        $this->reportProgress($state, $context);
    }
}

test('it addresses the requester own private channel', function () {
    $event = new RunProgressed(7, 'run-1', RunState::Running, 'board-meetings');

    expect(RunProgressed::channelFor(7))->toBe('users.7.runs');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and((string) $channels[0])->toBe('private-users.7.runs');
});

test('the payload names what to re-read and never carries the result', function () {
    $payload = (new RunProgressed(
        7,
        'run-1',
        RunState::Finished,
        'board-meetings',
        RunSubject::of('board-meeting', 42),
        ['status' => 'ok'],
    ))->broadcastWith();

    // The fields the client's `RunProgress` type declares. Asserted by name
    // rather than compared wholesale so a field added later fails here instead
    // of quietly widening the contract the client mirrors.
    expect(array_keys($payload))->toBe(['run_id', 'state', 'channel', 'subject', 'context', 'occurred_at'])
        ->and($payload['run_id'])->toBe('run-1')
        ->and($payload['state'])->toBe('finished')
        ->and($payload['channel'])->toBe('board-meetings')
        ->and($payload['subject'])->toBe(['type' => 'board-meeting', 'id' => 42])
        ->and($payload['context'])->toBe(['status' => 'ok']);
});

test('a run about nothing says so rather than omitting the key', function () {
    // Always present, null included - the same rule the stored summary's
    // `pending` key follows. A client must not have to tell "this run is about
    // nothing" from "an older payload that had no subject key at all".
    $payload = (new RunProgressed(7, 'run-1', RunState::Running, 'imports'))->broadcastWith();

    expect(array_key_exists('subject', $payload))->toBeTrue()
        ->and($payload['subject'])->toBeNull();
});

test('a subject keeps the id it was given, string or integer', function () {
    // The client's route params arrive as strings while its cached data arrives
    // as integers, and a subject that silently normalised would compare unequal
    // to the same id read from a payload - which is exactly the kind of quiet
    // mismatch that makes an event appear to be about nothing.
    expect(RunSubject::of('board-meeting', 42)->toArray())->toBe(['type' => 'board-meeting', 'id' => 42])
        ->and(RunSubject::of('board-meeting', '42')->toArray())->toBe(['type' => 'board-meeting', 'id' => '42']);
});

test('a subject refuses to be empty', function () {
    // Silently accepting a blank type or id would produce an event that routes
    // nowhere, which reads as a lost event rather than a bug at the call site.
    expect(fn () => RunSubject::of('', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RunSubject::of('board-meeting', ''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RunSubject::of('board-meeting', 0))->toThrow(InvalidArgumentException::class);
});

test('a run reports its states when somebody is listening', function () {
    Event::fake([RunProgressed::class]);

    $job = (new FakeProgressJob)->reportProgressTo(7, 'run-1', 'board-meetings');
    $job->report(RunState::Queued);
    $job->report(RunState::Running);
    $job->report(RunState::Finished, ['status' => 'ok']);

    Event::assertDispatchedTimes(RunProgressed::class, 3);
    Event::assertDispatched(RunProgressed::class, fn (RunProgressed $e): bool => $e->userId === 7
        && $e->runId === 'run-1'
        && $e->state === RunState::Running
        && $e->channel === 'board-meetings');
});

test('a run with no requester stays silent', function () {
    Event::fake([RunProgressed::class]);

    // The scheduled/CLI shape: a real job, nobody watching. Reporting must be a
    // no-op rather than an error, because a job that cannot report should not
    // be a job that cannot run.
    (new FakeProgressJob)->report(RunState::Running);
    (new FakeProgressJob)->reportProgressTo(null, 'run-1', 'board-meetings')->report(RunState::Running);

    Event::assertNotDispatched(RunProgressed::class);
});

test('a broken broadcast cannot fail the work', function () {
    // The whole point of swallowing here: a progress update is a courtesy, and
    // the run's actual outcome is written to the database either way. Letting
    // the exception escape would turn a cosmetic channel into a retry of the
    // whole model call - paying twice for one summary.
    (new FakeProgressJob)
        ->reportProgressTo(7, 'run-1', 'board-meetings')
        ->report(RunState::Running);
})->throwsNoExceptions();

test('only the two terminal states are terminal', function () {
    // Asserted through the enum's own predicate rather than by listing values,
    // so a state added later cannot become terminal by accident.
    expect(RunState::Queued->isTerminal())->toBeFalse()
        ->and(RunState::Running->isTerminal())->toBeFalse()
        ->and(RunState::Finished->isTerminal())->toBeTrue()
        ->and(RunState::Failed->isTerminal())->toBeTrue();
});
