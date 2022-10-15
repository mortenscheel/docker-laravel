<?php

return [
    'default' => 'project',
    'disks' => [
        'project' => [
            'driver' => 'local',
            'root' => getcwd(),
        ],
    ],
];
