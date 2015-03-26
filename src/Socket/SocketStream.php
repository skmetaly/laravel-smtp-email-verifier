<?php

namespace Skmetaly\EmailVerifier\Socket;

use Skmetaly\EmailVerifier\Exceptions\SMTPConnectionFailed;
use Skmetaly\EmailVerifier\Exceptions\SMTPUnexpectedResponse;
use Socket\Raw\Exception;


/**
 * Class Socket
 *
 * Simple and lightweight OOP wrapper for the low level sockets extension (ext-sockets)
 * Based upon https://github.com/clue/socket-raw
 * @package Skmetaly\EmailVerifier
 */
class SocketStream
{

    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var int
     */
    protected $timeout = 3;

    /**
     * @var
     */
    protected $current_response;

    /**
     * @var bool
     */
    protected $encrypted = false;

    /**
     *  SOCKET DEFAULT LENGTH
     */
    const SOCKET_DEFAULT_LENGTH = 1024;

    /**
     *  SOCKET RESPONSE
     */
    const SMTP_CONNECTION_SUCCESS = 220;

    /**
     *  SOCKET RESPONSE
     */
    const SMTP_CONNECTION_VALID = 250;

    /**
     *  SOCKET RESPONSE
     */
    const SMTP_USER_NOT_LOCAL = 251;

    /**
     *  SOCKET RESPONSE
     */
    const SMTP_MAILBOX_UNAVAILABLE = 550;

    /**
     * Default constructor
     *
     * @param $address
     */
    public function __construct($address = null)
    {
        if ($address) {

            $this->connect($address);
        }
    }

    /**
     * @param $address
     *
     * @param $port
     *
     * @return $this
     * @throws SMTPConnectionFailed
     */
    public function connect($address, $port = null)
    {
        if (!$port) {

            list($address, $port) = $this->formatAddress($address);
        }

        $this->resource = $this->create($address, $port, 'tcp');

        if (!$this->connected()) {

            throw new SMTPConnectionFailed();
        }

        $this->setTimeout($this->timeout);

        $this->setBlocking(1);
    }

    /**
     * read up to $length bytes from connect()ed / accept()ed socket
     *
     * @param int $length maximum length to read
     *
     * @return string
     * @throws Exception on error
     * @see  self::recv() if you need to pass flags
     * @uses socket_read()
     */
    public function read($length = null)
    {
        if ($length == null) {

            $length = self::SOCKET_DEFAULT_LENGTH;
        }

        $data = fgets($this->resource, $length);

        $response = '';

        while ($data) {

            $response .= $data;

            //  Usually this means there are more lines
            if ($data[ 3 ] != '-') {

                break;
            }

            $data = fgets($this->resource, $length);
        }

        if ($data === false) {

            throw Exception::createFromSocketResource($this->resource);
        }

        return $response;
    }

    /**
     * Writes the contents of string to the file stream pointed by the $this->resource
     * @access private
     *
     * @param      $query
     *
     * @param      $expectedResponseCode
     *
     * @param bool $debug
     *
     * @return string Returns a string of up to length - 1 bytes read from the file pointed to by handle.
     * If an error occurs, returns FALSE.
     * @throws SMTPUnexpectedResponse
     */
    public function send($query, $expectedResponseCode = null, $debug = false)
    {
        fputs($this->resource, $query . "\r\n", 1024);

        $response = $this->read();

        if ($debug) {

            echo $response;
        }

        if ($expectedResponseCode && !$this->checkResponseCode($response, $expectedResponseCode)) {

            throw new SMTPUnexpectedResponse();
        }

        return $response;
    }

    /**
     *  Close the socket stream
     */
    public function close()
    {
        fclose($this->resource);
    }

    /**
     * Reads all the line long the answer and analyze it.
     * @access private
     *
     * @param $length
     *
     * @return string Response code
     */
    public function getResponse($length = null)
    {
        if (!$length) {

            $length = $this->defaultLength;
        }

        if ($this->encrypted) {

            $this->current_response = $this->read($length);

        } else {

            $this->current_response = $this->read($length);
        }

        return $this->current_response;
    }

    /**
     * Return the current response in the queue
     * @return mixed
     */
    public function getCurrentResponse()
    {
        return $this->current_response;
    }

    /**
     * Create a socket stream resource based on the given parameters
     *
     * @param $address
     * @param $port
     *
     * @param $transportType
     *
     * @return resource
     */
    protected function create($address, $port, $transportType)
    {
        return fsockopen($address, $port, $errno, $errstr, $this->timeout);
    }

    /**
     * Format given address by splitting it into returned address and port set by reference
     *
     * @param string $address
     *
     * @return string address with port removed
     */
    protected function formatAddress($address)
    {
        // [::1]:2 => ::1 2
        // test:2 => test 2
        // ::1 => ::1
        // test => test
        $colon = strrpos($address, ':');

        // there is a colon and this is the only colon or there's a closing IPv6 bracket right before it
        if ($colon !== false && (strpos($address, ':') === $colon || strpos($address, ']') === ($colon - 1))) {

            $port = (int) substr($address, $colon + 1);
            $address = substr($address, 0, $colon);

            // remove IPv6 square brackets
            if (substr($address, 0, 1) === '[') {

                $address = substr($address, 1, - 1);
            }
        }

        return array($address, $port);
    }

    /**
     *
     * Enables the encryption for the socket
     *
     * @param     $enc
     * @param int $type
     *
     * @return mixed
     */
    public function enableEncryption($enc, $type = 1)
    {
        $this->encrypted = true;

        return stream_socket_enable_crypto($this->resource, $type, $enc);
    }

    /**
     * Disables the encryption for the socket
     * @return mixed
     */
    public function disableEncryption()
    {
        $this->encrypted = false;

        return stream_socket_enable_crypto($this->resource, 0);
    }

    /**
     * @param $timeout
     */
    private function setTimeout($timeout)
    {
        if ($this->resource) {

            stream_set_timeout($this->resource, $timeout);
        }
    }

    /**
     * Sets the blocking parameter
     *
     * @param $block
     *
     */
    private function setBlocking($block)
    {
        if ($this->resource) {

            stream_set_blocking($this->resource, $block);
        }
    }

    /**
     * Returns true if there is a connection with the attempted socket
     * @return bool
     */
    protected function connected()
    {
        return is_resource($this->resource);
    }

    /**
     *  Gets the response code that was received from the socket connection
     *
     * @param $response
     *
     * @return bool
     */
    public function getResponseCode($response)
    {
        $code = false;

        if ($response) {

            preg_match('/^(?<code>[0-9]{3})(.*)$/ims', $response, $matches);

            $code = isset($matches[ 'code' ]) ? $matches[ 'code' ] : false;
        }

        return $code;
    }

    /**
     * Verifies the current socket response with the expected response
     *
     * @param $response
     * @param $expectedResponseCode
     *
     * @return bool
     */
    private function checkResponseCode($response, $expectedResponseCode)
    {
        $responseCode = $this->getResponseCode($response);

        if (is_array($expectedResponseCode) && in_array($responseCode, $expectedResponseCode)) {

            return true;
        }

        if (!is_array($expectedResponseCode) && $responseCode == $expectedResponseCode) {

            return true;
        }

        return false;
    }
}