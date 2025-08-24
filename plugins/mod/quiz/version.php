<?php

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024012400;
$plugin->release   = '4.5.0 (Build: 20240124)';
$plugin->requires  = 2024011500;
$plugin->component = 'mod_quiz';
$plugin->maturity  = MATURITY_STABLE;
$plugin->dependencies = [
    'mod_question' => 2024011500,
    'core' => 2024011500
];