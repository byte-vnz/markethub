<?php
return [
    "number_format" => env("CONTRACT_NUMBER_FORMAT", "{prefix}-{period}-{sequence}"),
    "control_prefix" => env("CONTRACT_CONTROL_PREFIX", "SPPM"),
    "numbering_approval" => env("CONTRACT_NUMBERING_APPROVAL"),
    "rental_policy_approval" => env("CONTRACT_RENTAL_POLICY_APPROVAL"),
    "maximum_field_length" => 180,
];
