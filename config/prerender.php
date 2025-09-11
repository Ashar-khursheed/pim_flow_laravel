<?php

return [

    // Your Prerender.io token
    'prerender_token' => env('PRERENDER_TOKEN'),

    // Whitelist of crawler user agents (Google, Bing, etc.)
    'crawler_user_agents' => [
        'googlebot',
        'bingbot',
        'yahoo',
        'baiduspider',
        'facebookexternalhit',
        'twitterbot',
        'rogerbot',
        'linkedinbot',
        'embedly',
        'quora link preview',
        'showyoubot',
        'outbrain',
        'pinterest/0.',
        'developers.google.com/+/web/snippet',
        'slackbot',
    ],

    // Which requests should NOT be prerendered
    'ignored_paths' => [
        '/api', // skip API routes
    ],
];
