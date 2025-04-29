<?php

declare(strict_types=1);

return [
    'default' => 'project',
    'disks' => [
        'project' => [
            'driver' => 'local',
            'root' => getcwd(),
        ],
    ],
];
