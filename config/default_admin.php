<?php

return [
    "enabled" => env("SEED_DEFAULT_ADMIN", false),
    "name" => env("DEFAULT_ADMIN_NAME", "System Administrator"),
    "email" => env("DEFAULT_ADMIN_EMAIL", "admin@marketstall.local"),
    // Reserved configuration only: the current User schema and login have no username support.
    "username" => env("DEFAULT_ADMIN_USERNAME", "admin"),
    "password" => env("DEFAULT_ADMIN_PASSWORD"),
];
