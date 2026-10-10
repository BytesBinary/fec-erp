<?php

namespace App\Services\ResultPortal;

use App\Events\Portal\ExamCatalogUpdated;
use App\Exceptions\Domain\ResultPortalException;
use App\Models\PortalExam;
use Illuminate\Support\Collection;

/**
 * Saves the portal's exam list (CSE, EEE, Civil) so later pulls need no live
 * list. The first time a programme is synced everything is "known" (old
 * exams are not publications); after that an unseen exam id is "new".
 */
class ExamCatalogSync
{
    public function __construct(protected PortalClient $client, protected ExamCatalog $catalog) {}

    /**
     * @return Collection<int, PortalExam> the exams seen for the first time (empty on a first sync)
     *
     * @throws ResultPortalException
     */
    public function sync(): Collection
    {
        $new = collect();
        $delayMicroseconds = (int) config('result_portal.request_delay_ms') * 1000;

        foreach (array_values(array_unique(config('result_portal.department_programs'))) as $index => $programId) {
            if ($index > 0 && $delayMicroseconds > 0) {
                usleep($delayMicroseconds);
            }

            $listings = $this->catalog->parse($this->client->examOptionsHtml($programId));

            if ($listings === []) {
                throw new ResultPortalException("The portal returned an empty exam list for program {$programId}; not treating that as \"no news\".");
            }

            $baseline = ! PortalExam::query()->where('program_id', $programId)->exists();

            foreach ($listings as $listing) {
                $attributes = [
                    'program_id' => $programId,
                    'title' => $listing->title,
                    'kind' => $listing->kind,
                    'semester' => $listing->semester,
                    'exam_year' => $listing->examYear,
                    'session_tag' => $listing->sessionTag,
                    'last_seen_at' => now(),
                ];

                $exam = PortalExam::query()->where('portal_exam_id', $listing->id)->first();

                if ($exam === null) {
                    $exam = PortalExam::query()->create([...$attributes, 'portal_exam_id' => $listing->id, 'status' => $baseline ? 'known' : 'new', 'first_seen_at' => now()]);

                    if (! $baseline) {
                        $new->push($exam);
                    }

                    continue;
                }

                $exam->update($attributes);
            }
        }

        if ($new->isNotEmpty()) {
            event(new ExamCatalogUpdated($new));
        }

        return $new;
    }
}
