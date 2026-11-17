<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
    'daily_requests' => (int) env('ASSISTANT_DAILY_REQUESTS', 50),
    'max_output_tokens' => (int) env('ASSISTANT_MAX_OUTPUT_TOKENS', 8000),
];
