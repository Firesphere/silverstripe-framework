<?php

namespace SilverStripe\Security;

use Config;
use Member;
use ReflectionClass;

/**
 * Allows pluggable password encryption.
 * By default, this might be PHP's integrated sha1()
 * function, but could also be more sophisticated to facilitate
 * password migrations from other systems.
 * Use {@link register()} to add new implementations.
 *
 * Used in {@link Security::encrypt_password()}.
 *
 * @package framework
 * @subpackage security
 */
abstract class PasswordEncryptor
{

	/**
	 * @var array
	 * @config
	 */
	private static $encryptors = array();

	/**
	 * @return array Map of encryptor code to the used class.
	 */
	public static function get_encryptors()
	{
		return Config::inst()->get('PasswordEncryptor', 'encryptors');
	}

	/**
	 * @param String $algorithm
	 *
	 * @return PasswordEncryptor
	 * @throws PasswordEncryptor_NotFoundException
	 */
	public static function create_for_algorithm($algorithm)
	{
		$encryptors = self::get_encryptors();
		if (!array_key_exists($algorithm, $encryptors)) {
			throw new PasswordEncryptor_NotFoundException(
				sprintf('No implementation found for "%s"', $algorithm)
			);
		}

		$class = key($encryptors[$algorithm]);
		if (!class_exists($class)) {
			throw new PasswordEncryptor_NotFoundException(
				sprintf('No class found for "%s"', $class)
			);

		}
		$refClass = new ReflectionClass($class);
		if (!$refClass->getConstructor()) {
			return new $class;
		}

		$arguments = $encryptors[$algorithm];

		return $refClass->newInstanceArgs($arguments);
	}

	/**
	 * Return a string value stored in the {@link Member->Password} property.
	 * The password should be hashed with {@link salt()} if applicable.
	 *
	 * @param String $password Cleartext password to be hashed
	 * @param String $salt (Optional)
	 * @param Member $member (Optional)
	 *
	 * @return String Maximum of 512 characters.
	 */
	abstract public function encrypt($password, $salt = null, $member = null);

	/**
	 * Return a string value stored in the {@link Member->Salt} property.
	 *
	 * @uses RandomGenerator
	 * @return String Maximum of 50 characters
	 *
	 */
	public function salt()
	{
		$generator = new RandomGenerator();

		return substr($generator->randomToken('sha1'), 0, 50);
	}

	/**
	 * This usually just returns a strict string comparison,
	 * but is necessary for retain compatibility with password hashed
	 * with flawed algorithms - see {@link PasswordEncryptor_LegacyPHPHash} and
	 * {@link PasswordEncryptor_Blowfish}
	 *
	 * @param string $hash
	 * @param string $password
	 * @param null|string $salt
	 * @param null|Member $member
	 *
	 * @return bool
	 */
	public function check($hash, $password, $salt = null, $member = null)
	{
		return $hash === $this->encrypt($password, $salt, $member);
	}
}