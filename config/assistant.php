<?php

/*
|--------------------------------------------------------------------------
| In-app AI assistant (spec §5)
|--------------------------------------------------------------------------
|
| `provider`: `claude` (Anthropic Messages API, needs ANTHROPIC_API_KEY) or
| `fake` (scripted, used by tests). Without a key the widget degrades to
| plain feature search. NID and phone numbers are masked before anything is
| sent to the model (docs/DECISIONS.md D-014).
|
*/

return [

    'provider' => env('ASSISTANT_PROVIDER', 'claude'),

    'model' => env('ASSISTANT_MODEL', 'claude-sonnet-5-5'),

    'api_key' => env('ANTHROPIC_API_KEY'),

    'api_url' => env('ASSISTANT_API_URL', 'https://api.anthropic.com/v1/messages'),

    'api_version' => '2023-06-01',

    /*
     | Stream the answer as server-sent events. Switch off only where output
     | buffering cannot be flushed (the in-process browser-test server).
     */
    'stream' => (bool) env('ASSISTANT_STREAM', true),

    'max_tokens' => 1024,

    'timeout_seconds' => 40,

    /*
     | Max tool calls the assistant may make in one turn.
     */
    'max_tool_calls' => 6,

    /*
     | Messages kept per conversation when building the model context.
     */
    'history_messages' => 24,

    'rate_limit_per_minute' => (int) env('ASSISTANT_RATE_LIMIT', 20),

    'mask_keys' => ['phone', 'guardian_phone', 'emergency_contact_phone', 'nid_or_birth_reg', 'password', 'token'],

];
