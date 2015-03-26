<?php

namespace Skmetaly\EmailVerifier\SMTP;

use Exception;
use Skmetaly\EmailVerifier\Exceptions\SMTPConnectionFailed;
use Skmetaly\EmailVerifier\Socket;
use Skmetaly\EmailVerifier\Socket\SocketStream;
use Skmetaly\EmailVerifier\Verifier as VerifierInterface;
use Skmetaly\EmailVerifier\Exceptions\SMTPUnexpectedResponse;

/**
 * Class Verifier
 * @package Skmetaly\EmailVerifier\SMTP
 */
class Verifier implements VerifierInterface
{
    /**
     *  String input type identifier
     */
    const INPUT_TYPE_STRING = 'string';

    /**
     *  Array input type identifier
     */
    const INPUT_TYPE_ARRAY = 'array';

    /**
     *  Collection input type identifier
     */
    const INPUT_TYPE_COLLECTION = 'collection';

    /**
     * @var Socket
     */
    protected $socket;

    /**
     * @var override for the config
     */
    protected $fromDomain;

    /**
     * @var override for the from name
     */
    protected $fromName;

    /**
     *  Default constructor
     */
    public function __construct()
    {

    }

    /**
     *  Main function
     *  Checks the $input if contains any valid email addresses or if they exists on the MX servers
     *  Returns array of validated email addresses
     *
     * @param $input
     *
     * @return null
     */
    public function verify($input)
    {
        $inputType = $this->sortInput($input);

        //  Sort the email address by domain
        $emailDomains = $this->getByDomains($input);

        //  Get the mx records for all domains
        $mxRecords = $this->getMXRecords($emailDomains);

        //  Check SMTP for all domains / email addresses
        $validatedEmails = $this->checkSMTP($emailDomains, $mxRecords);

        //  Based on input type we call the appropriate function
        switch ($inputType) {

            case self::INPUT_TYPE_STRING:

                if (in_array($input, $validatedEmails)) {

                    return true;
                }

                return false;
                break;

            case self::INPUT_TYPE_ARRAY:

                return $validatedEmails;
                break;

            case self::INPUT_TYPE_COLLECTION:

                //  Not implemented
                break;
        }
    }

    /**
     * Verify all email addresses from an array domains
     *
     * @param $emailDomains
     * @param $mxRecords
     *
     * @return array
     */
    protected function checkSMTP($emailDomains, $mxRecords)
    {
        $validatedEmails = [];

        foreach ($emailDomains as $domain => $emails) {

            $domainMxRecords = $mxRecords[ $domain ];

            $continue = true;

            try {

                $socket = $this->getSocket($domainMxRecords);

                $socket->send('HELO ' . config('email-verifier.from_domain'), SocketStream::SMTP_CONNECTION_VALID);

                if (config('email-verifier.tls_connection') == 'true') {

                        if (config('email-verifier.tls_connection')) {

                            $socket->send('EHLO '. config('email-verifier.from_domain'), SocketStream::SMTP_CONNECTION_VALID);

                            $socket->send('STARTTLS', SocketStream::SMTP_CONNECTION_SUCCESS, false);
                            
                            $socket->enableEncryption(STREAM_CRYPTO_METHOD_TLS_CLIENT);
                            
                            $socket->send('EHLO ' . config('email-verifier.from_domain'), null);
                        }
                }

                $socket->send('MAIL FROM:<' . $this->getFromName() . '@' . $this->getFromDomain() . '>',
                    SocketStream::SMTP_CONNECTION_VALID);

                /*
                 *
                 *   This command does not affect any parameters or previously entered
                 *   commands.  It specifies no action other than that the receiver send a
                 *   "250 OK" reply.
                 *
                 *   This command has no effect on the reverse-path buffer, the forward-
                 *   path buffer, or the mail data buffer, and it may be issued at any
                 *   time.  If a parameter string is specified, servers SHOULD ignore it.

                 *   Idea from Tomaš Trkulja [zytzagoo]
                 *
                 */

                $socket->send('NOOP ' . config('email-verifier.from_domain'), SocketStream::SMTP_CONNECTION_VALID);

                if (config('email-verifier.test_catch_all')) {

                    $response = $this->testCatchAll($socket, $domain);

                    if ($response == false) {

                        $continue = false;
                    }
                }

                for ($i = 0; $i < count($emails) && $continue; $i ++) {

                    $email = $emails[ $i ];

                    try {

                        $socket->send('NOOP ' . config('email-verifier.from_domain'),
                            SocketStream::SMTP_CONNECTION_VALID);


                        $socket->send('RCPT TO: <' . $email . '>',
                            [SocketStream::SMTP_CONNECTION_VALID, SocketStream::SMTP_CONNECTION_SUCCESS]);


                        //  if we are here the email exists
                        $validatedEmails[ ] = $email;

                    } catch (SMTPUnexpectedResponse $e) {

                        // unexpected response - we don't validate this email
                    }
                }

                //  Close current connection
                $socket->send('RSET');

                $socket->send('QUIT');

                $socket->close();

            } catch (\Exception $e) {


            }
        }

        return $validatedEmails;
    }

    /**
     * Sorts $emails based on their domains
     * This is needed if we have more emails from the same domain
     *
     * @param $emails
     *
     * @return array
     */
    public function getByDomains($emails)
    {
        $type = $this->sortInput($emails);

        //  If we received a string we simply add it to an array
        if ($type == self::INPUT_TYPE_STRING) {

            $emails = [$emails];
        }

        $domains = [];

        //  Parse each email
        foreach ($emails as $email) {

            // Make sure we've got a valid email
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

                //  Get the domain part
                $domain = explode('@', $email)[ 1 ];

                if (!isset($domains[ $domain ])) {

                    $domains[ $domain ] = [];
                }

                //  Add the email to the domain
                $domains[ $domain ][ ] = $email;
            }
        }

        return $domains;
    }

    /**
     * Returns all MX records for a domain
     * The records are sorted by
     *
     * @param $domains
     *
     * @return array
     */
    public function getMXRecords($domains)
    {
        $mxRecords = [];

        foreach ($domains as $domain => $emails) {

            $mxHosts = [];
            $mxWeights = [];

            //  On windows systems getmxrr doesn't exist
            if (function_exists('getmxrr')) {

                getmxrr($domain, $mxHosts, $mxWeights);

                //  Order the mxhosts by their weight / importance
                array_multisort($mxWeights, $mxHosts);

                /**
                 * Add A-record as last chance (e.g. if no MX record is there).
                 */
                $mxHosts[ ] = $domain;

                $mxRecords[ $domain ] = $mxHosts;
            } else {

                //  Provide some sort of alternative here
            }
        }

        return $mxRecords;
    }

    /**
     * Returns what type of input receives
     *
     * @param $input
     *
     * @return string
     */
    protected function sortInput($input)
    {
        if (is_string($input)) {

            return self::INPUT_TYPE_STRING;
        }

        if (is_array($input)) {

            return self::INPUT_TYPE_ARRAY;
        }

        if (is_a($input, \Illuminate\Database\Eloquent\Collection)) {

            return self::INPUT_TYPE_COLLECTION;
        }
    }

    /**
     * Returns a socket connection for domain's mx records
     *
     * @param $domainMxRecords
     *
     * @return array
     * @throws SMTPConnectionFailed
     */
    protected function getSocket($domainMxRecords)
    {
        $socket = null;

        //  Try all the ports for each mxRecord
        $port = config('email-verifier.smtp_port');

        //  Iterate over all mx records of the domain
        foreach ($domainMxRecords as $mxRecord) {

            try {

                list($socket, $connected) = $this->initializeSocketConnection($mxRecord, $port);

                if ($connected) {

                    return $socket;
                }

            } catch (Exception $e) {

                continue;
            }
        }

        if ($socket == null) {

            throw new SMTPConnectionFailed();
        }

        return $socket;
    }

    /**
     * Tries to initiate a socket connection for an mx record
     *
     * @param $mxRecord
     * @param $port
     *
     * @return array
     */
    protected function initializeSocketConnection($mxRecord, $port)
    {
        $connected = false;

        $socket = null;

        try {
            
            //  Create the socket and try to connect to the mx record
            $socket = new SocketStream($mxRecord . ':' . $port);

            //  Read first 3 characters of the response . This is the response code
            $read = $socket->read(1024);
            
            $read = substr($read,0,3);

            if ($read == '220') {

                $connected = true;

                return array($socket, $connected);
            }

            return array($socket, $connected);

        } catch (SMTPConnectionFailed $e) {

            //  Connection failed we return false
            return array(null, false);
        }

    }

    /**
     * Test a SMTP socket connection for a catch all domain
     *
     * @param $socket
     * @param $domain
     *
     * @return bool
     */
    protected function testCatchAll($socket, $domain)
    {
        $fakeEmailAddress = 'catch-' . time() . '@' . $domain;

        try {

            $socket->send('RCPT TO: <' . $fakeEmailAddress . '>',
                [SocketStream::SMTP_CONNECTION_VALID, SocketStream::SMTP_CONNECTION_SUCCESS]);

            //  No exception - it means we had success with an invalid email address

            return false;

        } catch (SMTPUnexpectedResponse $e) {

            //  it means we didn't had success with an invalid email address, return true
            return true;
        }
    }

    /**
     * @return mixed
     */
    public function getFromDomain()
    {
        return ($this->fromDomain != '' ? $this->fromDomain : config('email-verifier.from_domain'));
    }

    /**
     * @param $domain
     */
    public function setFromDomain($domain)
    {
        $this->fromDomain = $domain;
    }

    /**
     * @return mixed
     */
    public function getFromName()
    {
        return ($this->fromName != '' ? $this->fromName : config('email-verifier.from_name'));
    }

    /**
     * @param $domain
     */
    public function setFromName($domain)
    {
        $this->fromDomain = $domain;
    }

    /**
     * @param $availableProtocols
     * @param $protocol
     *
     * @return bool
     */
    private function checkEhlo($availableProtocols, $protocol)
    {
        if (strpos($availableProtocols, '250-' . $protocol)) {

            return true;
        }

        return false;
    }

}