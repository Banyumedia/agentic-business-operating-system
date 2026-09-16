<?php

use App\Services\TerminologyResolver;

if (! function_exists('term')) {
    function term(string $key): string
    {
        return app(TerminologyResolver::class)->resolve($key);
    }
}
