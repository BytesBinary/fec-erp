<?php

/*
|--------------------------------------------------------------------------
| Student profile gate (spec §6)
|--------------------------------------------------------------------------
|
| `fields` lists every profile field the gate knows. `required` is only the
| seed of the `profile_required_fields` table, which super admin edits.
| `locked_after_clearance` fields cannot be changed by the student once a
| clearance request exists.
|
*/

return [

    'fields' => [
        'full_name_certificate' => ['label' => 'Full name (as on certificate)', 'required' => true],
        'father_name' => ['label' => "Father's name", 'required' => true],
        'mother_name' => ['label' => "Mother's name", 'required' => true],
        'date_of_birth' => ['label' => 'Date of birth', 'required' => true],
        'phone' => ['label' => 'Phone', 'required' => true],
        'email' => ['label' => 'Email', 'required' => true],
        'present_address' => ['label' => 'Present address', 'required' => true],
        'permanent_address' => ['label' => 'Permanent address', 'required' => true],
        'photo' => ['label' => 'Photo', 'required' => true],
        'guardian_phone' => ['label' => 'Guardian contact', 'required' => true],
        'blood_group' => ['label' => 'Blood group', 'required' => true],
        'nid_or_birth_reg' => ['label' => 'National ID / birth registration number', 'required' => true],
        'hall' => ['label' => 'Hall (if residential)', 'required' => true],
        'emergency_contact_phone' => ['label' => 'Emergency contact', 'required' => true],
        'guardian_name' => ['label' => "Guardian's name", 'required' => false],
        'emergency_contact_name' => ['label' => 'Emergency contact name', 'required' => false],
    ],

    'locked_after_clearance' => ['full_name_certificate', 'father_name', 'mother_name', 'date_of_birth'],

    'blood_groups' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],

    'photo' => [
        'disk' => 'public',
        'directory' => 'student-photos',
        'max_kb' => 2048,
        'mimes' => ['jpg', 'jpeg', 'png'],
        'aspect_ratio' => '35:45',
    ],

];
