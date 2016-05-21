<?php

namespace SilverStripe\Security;

use DB;
use Member;

/**
 * Uses MySQL's PASSWORD encryption. Requires an active DB connection.
 *
 * @package framework
 * @subpackage security
 */
class PasswordEncryptor_MySQLPassword extends PasswordEncryptor
{
	/**
	 * @param String $password
	 * @param null|string $salt
	 * @param null|Member $member
	 *
	 * @return mixed
	 */
	public function encrypt($password, $salt = null, $member = null)
	{
		return DB::prepared_query('SELECT PASSWORD(?)', array($password))->value();
	}

	/**
	 * @return bool
	 */
	public function salt()
	{
		return false;
	}
}
