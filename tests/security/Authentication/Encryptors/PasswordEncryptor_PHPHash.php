<?php

namespace SilverStripe\Security;

use Member;


/**
 * Encryption using built-in hash types in PHP.
 * Please note that the implemented algorithms depend on the PHP
 * distribution and architecture.
 *
 * @package framework
 * @subpackage security
 */
class PasswordEncryptor_PHPHash extends PasswordEncryptor
{

	protected $algorithm = 'sha1';

	/**
	 * @param String $algorithm A PHP built-in hashing algorithm as defined by hash_algos()
	 *
	 * @throws SecurityException
	 */
	public function __construct($algorithm)
	{
		if (!in_array($algorithm, hash_algos(), null)) {
			throw new SecurityException(
				sprintf('Hash algorithm "%s" not found in hash_algos()', $algorithm)
			);
		}

		$this->algorithm = $algorithm;
	}

	/**
	 * @return string
	 */
	public function getAlgorithm()
	{
		return $this->algorithm;
	}

	/**
	 * @param String $password
	 * @param null|string $salt
	 * @param null|Member $member
	 *
	 * @return mixed
	 */
	public function encrypt($password, $salt = null, $member = null)
	{
		return hash($this->algorithm, $password . $salt);
	}
}
