<?php

return [
    'scope_types' => [
        'global' => 'Global',
        'department' => 'Department',
        'hall' => 'Hall',
        'course' => 'Assigned courses',
        'self' => 'Self only',
    ],

    'channels' => [
        'web' => 'Web',
        'mcp' => 'MCP',
        'assistant' => 'Assistant',
        'system' => 'System',
    ],

    'errors' => [
        'forbidden' => 'You do not have permission to perform this action (:permission).',
        'forbidden_scope' => 'This record is outside your scope for :permission.',
        'inactive' => 'Your account is not active.',
        'not_found' => 'The requested :entity was not found.',
        'validation' => 'The given data was invalid.',
        'conflict' => 'The record was changed by someone else. Reload and try again.',
        'invalid_state' => 'This action is not allowed in the current state.',
        'rate_limited' => 'Too many requests. Please slow down.',
        'profile_incomplete' => 'Complete your profile before continuing.',
    ],

    'audit' => [
        'navigation_label' => 'Audit Log',
        'model_label' => 'audit log entry',
        'plural_model_label' => 'audit log',
    ],
];
