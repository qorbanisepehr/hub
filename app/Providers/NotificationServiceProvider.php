<?php

namespace App\Providers;

use App\Domains\Auth\Events\LoginFailed;
use App\Domains\BoardMeeting\Events\MeetingSummaryGenerated;
use App\Domains\Notification\Listeners\NotifyAccountOwnerOfFailedLogin;
use App\Domains\Notification\Listeners\NotifyQuestionnaireViewers;
use App\Domains\Notification\Listeners\NotifySummaryRequesterOfRun;
use App\Domains\Questionnaire\Events\QuestionnaireSubmitted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the notification domain to the event bus.
 *
 * Each domain dispatches its own events; Notification subscribes to the
 * business events it cares about. The listener defers to after-commit, so
 * only committed business data ever produces notifications.
 */
final class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(QuestionnaireSubmitted::class, NotifyQuestionnaireViewers::class);
        Event::listen(LoginFailed::class, NotifyAccountOwnerOfFailedLogin::class);
        Event::listen(MeetingSummaryGenerated::class, NotifySummaryRequesterOfRun::class);
    }
}
