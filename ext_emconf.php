<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 Hauler',
    'description' => 'A tool for developers and integrators to haul data from one TYPO3 installation to another.',
    'category' => 'misc',
    'author' => 'Dirk Wenzel',
    'author_email' => 'wenzel@cps-it.de',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0 - 13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
