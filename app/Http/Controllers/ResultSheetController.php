<?php

namespace App\Http\Controllers;

use App\Models\InstitutionSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\Results\ResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Print view of the signed-in student's published result sheet.
 */
class ResultSheetController extends Controller
{
    public function __invoke(Request $request, ResultService $results): View
    {
        $user = $request->user();
        assert($user instanceof User);

        $student = Student::query()->with(['user', 'department', 'program'])->where('user_id', $user->getKey())->firstOrFail();

        return view('results.print', [
            'student' => $student,
            'transcript' => $results->transcript($user, $student),
            'institution' => InstitutionSetting::current(),
        ]);
    }
}
