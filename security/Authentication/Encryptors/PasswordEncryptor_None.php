<?php
namespace SilverStripe\Security;

/**
 * Cleartext passwords (used in SilverStripe 2.1).
 * Also used when Security::$encryptPasswords is set to FALSE.
 * Not recommended.
 *
 * @deprecated DO NOT USE THIS ``ENCRYPTION``METHOD
 * @package framework
 * @subpackage security
 */
class PasswordEncryptor_None extends PasswordEncryptor
{
	/**
	 * @param String $password
	 * @param null $salt
	 * @param null $member
	 *
	 * @return String
	 */
	public function encrypt($password, $salt = null, $member = null)
	{
		return $password;
	}

	/**
	 * @return bool
	 */
	public function salt()
	{
		return false;
	}
}
