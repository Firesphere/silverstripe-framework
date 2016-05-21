<?php
/**
 * Created by IntelliJ IDEA.
 * User: simon
 * Date: 11/04/16
 * Time: 18:36
 */

namespace SilverStripe\Security;


use ArrayList;

class InsecurePasswordList
{
	protected static $passwords = array(
		'password',
		'123456',
		'12345678',
		'abc123',
		'qwerty',
		'monkey',
		'letmein',
		'dragon',
		'111111',
		'baseball',
		'iloveyou',
		'trustno1',
		'1234567',
		'sunshine',
		'master',
		'123123',
		'welcome',
		'shadow',
		'ashley',
		'football',
		'jesus',
		'michael',
		'ninja',
		'mustang',
		'password1',
	);

	/**
	 * @return ArrayList
	 */
	public static function getPasswords()
	{
		/** @var ArrayList $list */
		$list = ArrayList::create();
		foreach(self::$passwords as $value) {
			$list->push(array('password' => $value));
		}
		return $list;
	}

	/**
	 * @param array $passwords
	 */
	public static function setPasswords($passwords)
	{
		self::$passwords = $passwords;
	}
}
