<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Exports\BoardMeetingProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What one sheet export should contain, when the request says rather than the store.
 *
 * THE PARAMETER THAT LETS AN EXPORT DIALOG EXIST. Before this, what a meeting printed
 * came from stored state, so changing it meant saving it — which makes "just this
 * once" and "from now on" the same action. Somebody who wanted one page printed
 * differently had to alter every future export of that meeting to get it.
 *
 * NOTHING IS WRITTEN HERE. The list is honoured for the request that carried it and
 * forgotten, which is the whole point and the reason this is a query parameter read
 * on the way in rather than a settings screen.
 *
 * `sections` IS OPTIONAL AND ITS ABSENCE IS NOT AN EMPTY LIST. Absent means "whatever
 * this meeting is normally exported with", so an ordinary link keeps working and an
 * old bookmark keeps meaning what it meant. An explicit empty array would mean "print
 * nothing", and conflating the two is the mistake that makes a nullable parameter
 * dangerous — so only a caller that actually sends `sections` gets the strict reading.
 *
 * AN UNKNOWN NAME IS REJECTED RATHER THAN DROPPED. The sheet also drops names it
 * cannot build, because that is the right behaviour deep in a renderer where throwing
 * would lose the whole document. At the boundary the opposite is right: the caller
 * asked for something that does not exist, and a 422 names it, where a quietly
 * shortened document would be indistinguishable from the thing they asked for.
 */
final class ExportMeetingSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route already gates on `board-meeting.export`, which is this
        // request's whole job. Re-deriving it here would be a second answer to the
        // same question, free to disagree with the route.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sections' => ['sometimes', 'array'],
            'sections.*' => ['string', Rule::in(self::sectionKeys())],
        ];
    }

    /**
     * The sections to print, or null to use the stored or domain default.
     *
     * @return list<string>|null
     */
    public function sections(): ?array
    {
        if (! $this->has('sections')) {
            return null;
        }

        $sections = $this->validated('sections');

        return is_array($sections) ? array_values(array_filter(
            $sections,
            static fn (mixed $name): bool => is_string($name),
        )) : null;
    }

    /**
     * Every section this domain can print, in the order it prints them by default.
     *
     * READ FROM THE PROFILE rather than from the sheet's builders, which are private.
     * The two are asserted to agree in a test, because a validation list that drifts
     * from what can actually be built turns a request into a 422 about a section that
     * exists — the worst possible reason for a refusal.
     *
     * @return list<string>
     */
    public static function sectionKeys(): array
    {
        return array_map(
            static fn ($section): string => $section->key,
            (new BoardMeetingProfile)->sections(),
        );
    }
}
