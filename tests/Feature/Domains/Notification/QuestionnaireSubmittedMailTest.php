<?php

use App\Domains\Notification\Listeners\NotifyQuestionnaireViewers;
use App\Domains\Notification\Mail\QuestionnaireSubmittedMail;
use App\Domains\Questionnaire\Events\QuestionnaireSubmitted;
use App\Domains\Questionnaire\Models\Questionnaire;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Mail;

describe('questionnaire.submitted mail delivery', function () {
    it('queues the mail to questionnaire.view holders that have an email address', function () {
        Mail::fake();

        $viewer = createUserWithPermissions(['questionnaire.view']);
        $outsider = createUserWithPermissions();

        $questionnaire = Questionnaire::create(['status' => 'submitted']);

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        Mail::assertQueued(
            QuestionnaireSubmittedMail::class,
            fn (QuestionnaireSubmittedMail $mail): bool => $mail->hasTo($viewer->email),
        );
        Mail::assertNotQueued(
            QuestionnaireSubmittedMail::class,
            fn (QuestionnaireSubmittedMail $mail): bool => $mail->hasTo($outsider->email),
        );
    });

    it('skips recipients without a usable email without breaking the in-app notification', function () {
        Mail::fake();

        $viewer = createUserWithPermissions(['questionnaire.view']);
        $viewer->update(['email' => '']);

        $questionnaire = Questionnaire::create(['status' => 'submitted']);

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        Mail::assertNothingQueued();

        // The synchronous database channel (in-app source of truth) is untouched.
        expect($viewer->notifications()->count())->toBe(1);
    });

    it('renders the title, body, and review action link from the canonical contract', function () {
        Mail::fake();

        $viewer = createUserWithPermissions(['questionnaire.view']);
        $questionnaire = Questionnaire::create(['status' => 'submitted']);

        app(NotifyQuestionnaireViewers::class)->handle(new QuestionnaireSubmitted($questionnaire));

        Mail::assertQueued(QuestionnaireSubmittedMail::class, function (QuestionnaireSubmittedMail $mail) use ($questionnaire): bool {
            $mail->assertHasSubject(__('notifications.questionnaire_submitted.title'));
            $mail->assertSeeInHtml(__('notifications.questionnaire_submitted.body'));
            $mail->assertSeeInHtml(url('/questionnaires/'.$questionnaire->id));

            return true;
        });
    });

    it('is queued with bounded retries and backoff', function () {
        $reflection = new ReflectionClass(QuestionnaireSubmittedMail::class);

        expect($reflection->getAttributes(Tries::class))->not->toBeEmpty();
        expect($reflection->getAttributes(Backoff::class))->not->toBeEmpty();
        expect(new QuestionnaireSubmittedMail('1'))->toBeInstanceOf(ShouldQueue::class);
    });
});
