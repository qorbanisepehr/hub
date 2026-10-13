/**
 * The summary run's step vocabulary, and this feature's copy of it.
 *
 * The server sends the keys and nothing else — `preparing`, `prompting`,
 * `calling_model`, `parsing`, `storing` — and this file is where they become
 * Persian. That split is the same one the summary's status words and failure
 * reasons already use, and for the same reason: a string written into an event
 * is read again in whatever language the client is in next year, so it has to
 * be translatable rather than already translated.
 *
 * Unknown keys are shown as themselves rather than dropped. A step added on the
 * server before this file catches up would otherwise vanish silently, and a
 * narration that skips a phase looks like a run that jumped a step.
 */
export const SUMMARY_STEP_LABELS: Readonly<Record<string, string>> = {
    preparing: "در حال آماده‌سازی درخواست",
    prompting: "در حال نوشتن دستور به مدل",
    calling_model: "مدل در حال پاسخ دادن است",
    parsing: "در حال بررسی پاسخ مدل",
    storing: "در حال ثبت نتیجه روی جلسه",
};

/** One step's Persian label, falling back to the key it was given. */
export function summaryStepLabel(step: string): string {
    return SUMMARY_STEP_LABELS[step] ?? step;
}

/**
 * A short sentence per step, for the trail.
 *
 * Separate from the label because the label answers "where is it now" — a
 * status line — while a trail answers "what did it just do", and past tense in
 * English is past tense in Persian too. Reusing one string for both would make
 * the trail read as a list of statuses rather than of things that happened.
 */
export const SUMMARY_STEP_DONE_LABELS: Readonly<Record<string, string>> = {
    preparing: "درخواست آماده شد",
    prompting: "دستور مدل نوشته شد",
    calling_model: "پاسخ مدل رسید",
    parsing: "پاسخ بررسی شد",
    storing: "نتیجه روی جلسه ثبت شد",
};

export function summaryStepDoneLabel(step: string): string {
    return SUMMARY_STEP_DONE_LABELS[step] ?? step;
}