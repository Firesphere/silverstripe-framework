<?php

namespace SilverStripe\Security;

use Member;

/**
 * Legacy implementation for SilverStripe 2.1 - 2.3,
 * which had a design flaw in password hashing that caused
 * the hashes to differ between architectures due to
 * floating point precision problems in base_convert().
 * See http://open.silverstripe.org/ticket/3004
 *
 * @package framework
 * @subpackage security
 */
class PasswordEncryptor_LegacyPHPHash extends PasswordEncryptor_PHPHash
{
	/**
	 * @param String $password
	 * @param null $salt
	 * @param null|Member $member
	 *
	 * @return mixed
	 */
	public function encrypt($password, $salt = null, $member = null)
	{
		$password = parent::encrypt($password, $salt, $member);

		// Legacy fix: This shortening logic is producing unpredictable results.
		//
		// Convert the base of the hexadecimal password to 36 to make it shorter
		// In that way we can store also a SHA256 encrypted password in just 64
		// letters.
		return substr(base_convert($password, 16, 36), 0, 64);
	}

	/**
	 * @param string $hash
	 * @param string $password
	 * @param null|string $salt
	 * @param null|Member $member
	 *
	 * @return bool
	 */
	public function check($hash, $password, $salt = null, $member = null)
	{
		// Due to flawed base_convert() floating poing precision,
		// only the first 10 characters are consistently useful for comparisons.
		$substr = substr($this->encrypt($password, $salt, $member), 0, 10);
		return (0 === strpos($hash, $substr));
	}
}
