<?php

namespace Skmetaly\EmailVerifier;

interface Verifier{

    public function verify($emailAddress);
}