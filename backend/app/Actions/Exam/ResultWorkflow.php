<?php

namespace App\Actions\Exam;

use App\Enums\ResultStatus as S;
use App\Models\Exam;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Exam\ResultService;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move a class's results through the publishing workflow (spec §56), all
 * rows together, each step audited (spec §43, §55):
 *
 *   review    draft → review          (result.process)
 *   approve   review → approved       (result.publish)
 *   publish   approved → published    (result.publish) — students, parents see it
 *   return    review|approved → draft (result.publish) — for recalculation
 *   unpublish published → approved    (result.publish) — to correct and re-publish
 *
 * Permissions are checked by the routes; this checks the step is allowed
 * from where the results are (409 otherwise).
 */
class ResultWorkflow
{
    /** action => [from statuses, to status] */
    public const STEPS = [
        'review' => [[S::Draft], S::Review],
        'approve' => [[S::Review], S::Approved],
        'publish' => [[S::Approved], S::Published],
        'return' => [[S::Review, S::Approved], S::Draft],
        'unpublish' => [[S::Published], S::Approved],
    ];

    /** For messages: "can't be {verb}". */
    private const VERBS = [
        'review' => 'sent for review',
        'approve' => 'approved',
        'publish' => 'published',
        'return' => 'returned to draft',
        'unpublish' => 'unpublished',
    ];

    /** For the audit action name: results.sent_for_review, results.published, … */
    private const AUDIT = [
        'review' => 'sent_for_review',
        'approve' => 'approved',
        'publish' => 'published',
        'return' => 'returned',
        'unpublish' => 'unpublished',
    ];

    /** Steps that move results towards publishing. */
    private const FORWARD = ['review', 'approve', 'publish'];

    public function __construct(private readonly ResultService $results) {}

    /** @return int how many results moved */
    public function execute(User $user, Exam $exam, SchoolClass $class, string $action): int
    {
        [$from, $to] = self::STEPS[$action];

        return DB::transaction(function () use ($user, $exam, $class, $action, $from, $to) {
            Exam::query()->whereKey($exam->id)->lockForUpdate()->first();

            $results = Result::query()->where('exam_id', $exam->id)->where('class_id', $class->id);
            $current = (clone $results)->first(['status'])?->status;
            if ($current === null) {
                throw ValidationException::withMessages(['class_id' => "Calculate {$class->name}'s results first."]);
            }
            abort_unless(
                in_array($current, $from, true),
                409,
                "{$class->name}'s results are {$current->label()}, so they can't be ".self::VERBS[$action].'.',
            );
            if (in_array($action, self::FORWARD, true)) {
                $this->ensureCurrent($exam, $class);
            }

            $count = (clone $results)->update([
                'status' => $to->value,
                ...match ($action) {
                    'publish' => ['published_at' => now(), 'published_by' => $user->id],
                    'unpublish', 'return' => ['published_at' => null, 'published_by' => null],
                    default => [],
                },
            ]);

            Audit::record($user, 'results.'.self::AUDIT[$action], 'results', $exam,
                old: ['class_id' => $class->id, 'status' => $current->value],
                new: ['class_id' => $class->id, 'status' => $to->value, 'results' => $count],
            );

            return $count;
        });
    }

    /**
     * Results only move forward while they still match their inputs: every
     * mark sheet submitted, and calculated after the grade scale last changed.
     */
    private function ensureCurrent(Exam $exam, SchoolClass $class): void
    {
        $open = $this->results->sheets($exam, $class->id)->where('status', '!=', 'submitted');
        abort_if($open->isNotEmpty(), 409, "Some of {$class->name}'s marks are no longer submitted. Return the results to draft, then recalculate.");
        abort_if($this->results->stale($exam, $class->id), 409, "{$class->name}'s results were calculated before the grade scale changed. Return them to draft and recalculate.");
    }
}
