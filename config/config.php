<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Time
    |--------------------------------------------------------------------------
    |
    | Cache time for get data post
    |
    | - set zero for remove cache
    | - set null for forever
    |
    | - unit: minutes
    */

    "cache_time" => env("POST_CACHE_TIME", 0),

];
