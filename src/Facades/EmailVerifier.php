<?php
namespace Skmetaly\EmailVerifier\Facades;

use Illuminate\Support\Facades\Facade;

class EmailVerifier extends Facade{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'Skmetaly\EmailVerifier\Verifier'; }
}