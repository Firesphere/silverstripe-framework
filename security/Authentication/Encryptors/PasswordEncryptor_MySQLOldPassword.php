<?php

namespace SilverStripe\Security;

use DB;
use Member;

/**
 * Uses MySQL's OLD_PASSWORD encyrption. Requires an active DB connection.
 *
 * @package framework
 * @subpackage security
 */
class PasswordEncryptor_MySQLOldPassword extends PasswordEncryptor
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
		return DB::prepared_query('SELECT OLD_PASSWORD(?)', array($password))->value();
	}

	/**
	 * @return bool
	 */
	public function salt()
	{
		return false;
	}
}
