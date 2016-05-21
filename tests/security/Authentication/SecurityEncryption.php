<?php

namespace SilverStripe\Security;


use Config;
use Member;
use SS_HTTPResponse_Exception;

/**
 * Class SecurityEncryption
 *
 * @package SilverStripe\Security
 */
class SecurityEncryption
{

	/**
	 * Encrypt a password according to the current password encryption settings.
	 * If the settings are so that passwords shouldn't be encrypted, the
	 * result is simple the clear text password with an empty salt except when
	 * a custom algorithm ($algorithm parameter) was passed.
	 *
	 * @param string $password The password to encrypt
	 * @param string $salt Optional: The salt to use. If it is not passed, but
	 *  needed, the method will automatically create a
	 *  random salt that will then be returned as return value.
	 * @param string $algorithm Optional: Use another algorithm to encrypt the
	 *  password (so that the encryption algorithm can be changed over the time).
	 * @param Member $member Optional
	 *
	 * @return mixed Returns an associative array containing the encrypted
	 *  password and the used salt in the form:
	 * <code>
	 * array(
	 * 'password' => string,
	 * 'salt' => string,
	 * 'algorithm' => string,
	 * 'encryptor' => PasswordEncryptor instance
	 * )
	 * </code>
	 * If the passed algorithm is invalid, FALSE will be returned.
	 * @throws SS_HTTPResponse_Exception
	 * @see encrypt_passwords()
	 */
	public static function encrypt_password($password, $salt = null, $algorithm = null, $member = null)
	{
		// Fall back to the default encryption algorithm
		if ($algorithm === null) {
			$algorithm = Config::inst()->get('Security', 'password_encryption_algorithm');
		}

		try {
			$encryptor = PasswordEncryptor::create_for_algorithm($algorithm);
		} catch (PasswordEncryptor_NotFoundException $encryptor) {
			throw new SS_HTTPResponse_Exception($encryptor->getMessage());
		}

		// New salts will only need to be generated if the password is hashed for the first time
		$salt = $salt ?: $encryptor->salt();

		return array(
			'password'  => $encryptor->encrypt($password, $salt, $member),
			'salt'      => $salt,
			'algorithm' => $algorithm,
			'encryptor' => $encryptor
		);
	}

}
