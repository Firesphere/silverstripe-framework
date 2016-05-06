<?php

namespace Silverstripe\Security;

use Exception;

/**
 * Class SecurityException
 *
 * @package Silverstripe\Security
 */
class SecurityException extends Exception
{
	/**
	 * SecurityException constructor.
	 *
	 * @inheritdoc
	 *
	 * @param string $message
	 * @param int $code
	 * @param null $previous
	 */
	public function __construct($message = 'SilverStripe Security exception', $code = 255, $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

}
