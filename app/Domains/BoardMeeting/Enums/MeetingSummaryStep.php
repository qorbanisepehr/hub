<?php

namespace App\Domains\BoardMeeting\Enums;

/**
 * Which part of a summary run is under way right now.
 *
 * Stable keys, never prose, for exactly the reason the summary's status words
 * and failure reasons are stable keys: a string written into an event is read
 * again in whatever language the client is in next year, so it must be
 * translatable rather than already translated. The client owns that copy.
 *
 * Named phases rather than a percentage, and never a number. This run has four
 * steps that take wildly different amounts of time — assembling the prompt is
 * milliseconds, the model call is a minute — so any progress figure would be
 * invented by the sender and believed by the receiver, which is worse than
 * saying plainly which step you are on. `App\Support\Realtime\RunState` refuses
 * percentages for the same reason, and these are the finer grain of the same
 * refusal.
 *
 * Ordered so a client can render progress as a sense of how far along the work
 * is, which is true here in a way it would not be for a job whose steps are
 * interchangeable.
 */
enum MeetingSummaryStep: string
{
    /** Reading the meeting and deciding what it is being asked for. */
    case Preparing = 'preparing';

    /** Assembling the prompt from the meeting's own content. */
    case Prompting = 'prompting';

    /** The model is answering. Usually the whole of the wait. */
    case CallingModel = 'calling_model';

    /** Reading the answer back and reconciling it against what was requested. */
    case Parsing = 'parsing';

    /** Writing the outcome onto the row. */
    case Storing = 'storing';
}
