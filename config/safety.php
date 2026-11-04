<?php

return [
    'inactivity_seconds' => (int) env('JOURNEY_INACTIVITY_SECONDS', 900),
    'escalation_delay_seconds' => (int) env('JOURNEY_ESCALATION_DELAY_SECONDS', 120),
];
