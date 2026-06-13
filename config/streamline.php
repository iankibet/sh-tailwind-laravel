<?php

return [
    'class_namespace' => 'App\\Streams',
    'class_postfix' => 'Stream',
    'route' => 'api/streamline',
    'flat_route' => 'api/streamline',
    'middleware' => ['auth:sanctum'],
    'guest_streams' => [
        'auth/guest',
    ],
];
