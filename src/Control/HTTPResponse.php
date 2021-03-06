<?php

namespace SilverStripe\Control;

use InvalidArgumentException;
use Monolog\Handler\HandlerInterface;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\Requirements;

/**
 * Represents a response returned by a controller.
 */
class HTTPResponse
{
    use Injectable;

    /**
     * @var array
     */
    protected static $status_codes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Request Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    );

    /**
     * @var array
     */
    protected static $redirect_codes = array(
        301,
        302,
        303,
        304,
        305,
        307
    );

    /**
     * @var int
     */
    protected $statusCode = 200;

    /**
     * @var string
     */
    protected $statusDescription = "OK";

    /**
     * HTTP Headers like "content-type: text/xml"
     *
     * @see http://en.wikipedia.org/wiki/List_of_HTTP_headers
     * @var array
     */
    protected $headers = array(
        "content-type" => "text/html; charset=utf-8",
    );

    /**
     * @var string
     */
    protected $body = null;

    /**
     * Create a new HTTP response
     *
     * @param string $body The body of the response
     * @param int $statusCode The numeric status code - 200, 404, etc
     * @param string $statusDescription The text to be given alongside the status code.
     *  See {@link setStatusCode()} for more information.
     */
    public function __construct($body = null, $statusCode = null, $statusDescription = null)
    {
        $this->setBody($body);
        if ($statusCode) {
            $this->setStatusCode($statusCode, $statusDescription);
        }
    }

    /**
     * @param int $code
     * @param string $description Optional. See {@link setStatusDescription()}.
     *  No newlines are allowed in the description.
     *  If omitted, will default to the standard HTTP description
     *  for the given $code value (see {@link $status_codes}).
     * @return $this
     */
    public function setStatusCode($code, $description = null)
    {
        if (isset(self::$status_codes[$code])) {
            $this->statusCode = $code;
        } else {
            throw new InvalidArgumentException("Unrecognised HTTP status code '$code'");
        }

        if ($description) {
            $this->statusDescription = $description;
        } else {
            $this->statusDescription = self::$status_codes[$code];
        }
        return $this;
    }

    /**
     * The text to be given alongside the status code ("reason phrase").
     * Caution: Will be overwritten by {@link setStatusCode()}.
     *
     * @param string $description
     * @return $this
     */
    public function setStatusDescription($description)
    {
        $this->statusDescription = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return string Description for a HTTP status code
     */
    public function getStatusDescription()
    {
        return str_replace(array("\r","\n"), '', $this->statusDescription);
    }

    /**
     * Returns true if this HTTP response is in error
     *
     * @return bool
     */
    public function isError()
    {
        $statusCode = $this->getStatusCode();
        return $statusCode && ($statusCode < 200 || $statusCode > 399);
    }

    /**
     * @param string $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body ? (string) $body : $body; // Don't type-cast false-ish values, eg null is null not ''
        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Add a HTTP header to the response, replacing any header of the same name.
     *
     * @param string $header Example: "content-type"
     * @param string $value Example: "text/xml"
     * @return $this
     */
    public function addHeader($header, $value)
    {
        $header = strtolower($header);
        $this->headers[$header] = $value;
        return $this;
    }

    /**
     * Return the HTTP header of the given name.
     *
     * @param string $header
     * @returns string
     */
    public function getHeader($header)
    {
        $header = strtolower($header);
        if (isset($this->headers[$header])) {
            return $this->headers[$header];
        }
        return null;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Remove an existing HTTP header by its name,
     * e.g. "Content-Type".
     *
     * @param string $header
     * @return $this
     */
    public function removeHeader($header)
    {
        strtolower($header);
        unset($this->headers[$header]);
        return $this;
    }

    /**
     * @param string $dest
     * @param int $code
     * @return $this
     */
    public function redirect($dest, $code = 302)
    {
        if (!in_array($code, self::$redirect_codes)) {
            trigger_error("Invalid HTTP redirect code {$code}", E_USER_WARNING);
            $code = 302;
        }
        $this->setStatusCode($code);
        $this->addHeader('location', $dest);
        return $this;
    }

    /**
     * Send this HTTPResponse to the browser
     */
    public function output()
    {
        // Attach appropriate X-Include-JavaScript and X-Include-CSS headers
        if (Director::is_ajax()) {
            Requirements::include_in_response($this);
        }

        if ($this->isRedirect() && headers_sent()) {
            $this->htmlRedirect();
        } else {
            $this->outputHeaders();
            $this->outputBody();
        }
    }

    /**
     * Generate a browser redirect without setting headers
     */
    protected function htmlRedirect()
    {
        $headersSent = headers_sent($file, $line);
        $location = $this->getHeader('location');
        $url = Director::absoluteURL($location);
        $urlATT = Convert::raw2htmlatt($url);
        $urlJS = Convert::raw2js($url);
        $title = (Director::isDev() && $headersSent)
            ? "{$urlATT}... (output started on {$file}, line {$line})"
            : "{$urlATT}...";
        echo <<<EOT
<p>Redirecting to <a href="{$urlATT}" title="Click this link if your browser does not redirect you">{$title}</a></p>
<meta http-equiv="refresh" content="1; url={$urlATT}" />
<script type="application/javascript">setTimeout(function(){
	window.location.href = "{$urlJS}";
}, 50);</script>
EOT
        ;
    }

    /**
     * Output HTTP headers to the browser
     */
    protected function outputHeaders()
    {
        $headersSent = headers_sent($file, $line);
        if (!$headersSent) {
            $method = sprintf(
                "%s %d %s",
                $_SERVER['SERVER_PROTOCOL'],
                $this->getStatusCode(),
                $this->getStatusDescription()
            );
            header($method);
            foreach ($this->getHeaders() as $header => $value) {
                    header("{$header}: {$value}", true, $this->getStatusCode());
            }
        } elseif ($this->getStatusCode() >= 300) {
            // It's critical that these status codes are sent; we need to report a failure if not.
            user_error(
                sprintf(
                    "Couldn't set response type to %d because of output on line %s of %s",
                    $this->getStatusCode(),
                    $line,
                    $file
                ),
                E_USER_WARNING
            );
        }
    }

    /**
     * Output body of this response to the browser
     */
    protected function outputBody()
    {
        // Only show error pages or generic "friendly" errors if the status code signifies
        // an error, and the response doesn't have any body yet that might contain
        // a more specific error description.
        $body = $this->getBody();
        if ($this->isError() && empty($body)) {
            /** @var HandlerInterface $handler */
            $handler = Injector::inst()->get(HandlerInterface::class);
            $formatter = $handler->getFormatter();
            echo $formatter->format(array(
                'code' => $this->statusCode
            ));
        } else {
            echo $this->body;
        }
    }

    /**
     * Returns true if this response is "finished", that is, no more script execution should be done.
     * Specifically, returns true if a redirect has already been requested
     *
     * @return bool
     */
    public function isFinished()
    {
        return $this->isRedirect() || $this->isError();
    }

    /**
     * Determine if this response is a redirect
     *
     * @return bool
     */
    public function isRedirect()
    {
        return in_array($this->getStatusCode(), self::$redirect_codes);
    }
}
