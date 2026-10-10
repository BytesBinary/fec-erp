<?php

namespace Database\Seeders\Testing;

/**
 * Fixed identifiers of the deterministic `seed:test` dataset, shared by the
 * seeder, Pest feature tests, browser (E2E) tests and MCP fixtures.
 * Every seeded account uses {@see self::PASSWORD}.
 */
final class TestDataset
{
    public const PASSWORD = 'password';

    public const SUPER_ADMIN = 'superadmin@fec.test';

    public const ADMIN_OFFICE = 'office@fec.test';

    public const HEAD_OF_INSTITUTION = 'head@fec.test';

    public const PRINCIPAL = 'principal@fec.test';

    /** Department head of CSE (also a teacher). */
    public const DEPT_HEAD_CSE = 'head.cse@fec.test';

    /** Department head of EEE (also a teacher). */
    public const DEPT_HEAD_EEE = 'head.eee@fec.test';

    /** Provost of hall BJH. */
    public const PROVOST_A = 'provost.bjh@fec.test';

    /** Provost of hall SKH. */
    public const PROVOST_B = 'provost.skh@fec.test';

    public const LIBRARIAN = 'librarian@fec.test';

    /** CSE teacher assigned to CSE-1101. */
    public const TEACHER = 'teacher@fec.test';

    /** Profile not completed (profile gate). */
    public const STUDENT_INCOMPLETE = 'student.incomplete@fec.test';

    /** Profile complete, program not finished (not eligible for clearance). */
    public const STUDENT_UNFINISHED = 'student.unfinished@fec.test';

    /** Eligible for clearance, resident of hall BJH. */
    public const STUDENT_ELIGIBLE = 'student.eligible@fec.test';

    /** Eligible, non-residential (hall stage skipped). */
    public const STUDENT_NON_RESIDENT = 'student.nonresident@fec.test';

    /** Eligible, but with an outstanding library loan; resident of hall SKH, EEE. */
    public const STUDENT_LIBRARY_LOAN = 'student.libraryloan@fec.test';

    /**
     * Hand-calculated expectations for the seeded published results
     * (best attempt counts; CGPA rounded half up to 2 decimals).
     *
     * @var array<string, array{cgpa: string, earned_credits: float, semesters: array<string, string>}>
     */
    public const EXPECTED_RESULTS = [
        self::STUDENT_ELIGIBLE => ['cgpa' => '3.56', 'earned_credits' => 12.0, 'semesters' => ['SP2024' => '3.83', 'FA2024' => '1.63', 'SP2025' => '3.50']],
        self::STUDENT_UNFINISHED => ['cgpa' => '3.33', 'earned_credits' => 4.5, 'semesters' => ['SP2024' => '3.33']],
        self::STUDENT_NON_RESIDENT => ['cgpa' => '3.53', 'earned_credits' => 12.0, 'semesters' => ['SP2024' => '3.92', 'FA2024' => '3.38', 'SP2025' => '3.00']],
        self::STUDENT_LIBRARY_LOAN => ['cgpa' => '3.59', 'earned_credits' => 12.0, 'semesters' => ['SP2024' => '4.00', 'FA2024' => '3.35']],
    ];

    public const DEPT_CSE = 'CSE';

    public const DEPT_EEE = 'EEE';

    public const HALL_A = 'BJH';

    public const HALL_B = 'SKH';

    /**
     * One account per role, keyed by role key (first account wins for roles with two).
     *
     * @return array<string, string>
     */
    public static function accountsByRole(): array
    {
        return [
            'super_admin' => self::SUPER_ADMIN,
            'admin_office' => self::ADMIN_OFFICE,
            'head_of_institution' => self::HEAD_OF_INSTITUTION,
            'principal' => self::PRINCIPAL,
            'department_head' => self::DEPT_HEAD_CSE,
            'hall_provost' => self::PROVOST_A,
            'librarian' => self::LIBRARIAN,
            'teacher' => self::TEACHER,
            'student' => self::STUDENT_ELIGIBLE,
        ];
    }
}
