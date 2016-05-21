<?php

namespace SilverStripe\Security;

use Config;
use Deprecation;

/**
 * This is not really a trait, but it's the easiest way to get all the deprecated
 * methods out of the way in Security.
 * @deprecated functions as of SilverStripe 4
 */
trait SecurityDeprecation
{


	/**
	 * Get location of word list file
	 *
	 * @deprecated 4.0 Use the "Security.word_list" config setting instead
	 */
	public static function get_word_list()
	{
		Deprecation::notice('4.0', 'Use the "Security.word_list" config setting instead');

		return Config::inst()->get('Security', 'word_list');
	}

	/**
	 * Set a custom log-in URL if you have built your own log-in page.
	 *
	 * @deprecated 4.0 Use the "Security.login_url" config setting instead.
	 *
	 * @param $loginUrl
	 */
	public static function set_login_url($loginUrl)
	{
		Deprecation::notice('4.0', 'Use the "Security.login_url" config setting instead');
		self::config()->login_url = $loginUrl;
	}

	/**
	 * Get the default login dest.
	 *
	 * @deprecated 4.0 Use the "Security.default_login_dest" config setting instead
	 */
	public static function default_login_dest()
	{
		Deprecation::notice('4.0', 'Use the "Security.default_login_dest" config setting instead');

		return Config::inst()->get('Security', 'default_login_dest');
	}

	/**
	 * @deprecated 4.0 Use the "Security.default_login_dest" config setting instead
	 */
	public static function set_default_login_dest($dest)
	{
		Deprecation::notice('4.0', 'Use the "Security.default_login_dest" config setting instead');
		self::config()->default_login_dest = $dest;
	}

	/**
	 * Enable or disable recording of login attempts
	 * through the {@link LoginRecord} object.
	 *
	 * @deprecated 4.0 Use the "Security.login_recording" config setting instead
	 *
	 * @param boolean $bool
	 */
	public static function set_login_recording($bool)
	{
		Deprecation::notice('4.0', 'Use the "Security.login_recording" config setting instead');
		self::config()->login_recording = (bool)$bool;
	}

	/**
	 * @deprecated 4.0 Use the "Security.login_recording" config setting instead
	 * @return boolean
	 */
	public static function login_recording()
	{
		Deprecation::notice('4.0', 'Use the "Security.login_recording" config setting instead');

		return Config::inst()->get('Security', 'login_recording');
	}

	/**
	 * Set the password encryption algorithm
	 *
	 * @deprecated 4.0 Use the "Security.password_encryption_algorithm" config setting instead
	 *
	 * @param string $algorithm One of the available password encryption
	 *  algorithms determined by {@link Security::get_encryption_algorithms()}
	 *
	 * @return bool Returns TRUE if the passed algorithm was valid, otherwise FALSE.
	 */
	public static function set_password_encryption_algorithm($algorithm)
	{
		Deprecation::notice('4.0', 'Use the "Security.password_encryption_algorithm" config setting instead');
		self::config()->password_encryption_algorithm = $algorithm;
	}

	/**
	 * @deprecated 4.0 Use the "Security.password_encryption_algorithm" config setting instead
	 * @return String
	 */
	public static function get_password_encryption_algorithm()
	{
		Deprecation::notice('4.0', 'Use the "Security.password_encryption_algorithm" config setting instead');

		return Config::inst()->get('Security', 'password_encryption_algorithm');
	}

	/**
	 * Set location of word list file
	 *
	 * @deprecated 4.0 Use the "Security.word_list" config setting instead
	 *
	 * @param string $wordListFile Location of word list file
	 */
	public static function set_word_list($wordListFile)
	{
		Deprecation::notice('4.0', 'Use the "Security.word_list" config setting instead');
		self::config()->word_list = $wordListFile;
	}

	/**
	 * Set the default message set used in permissions failures.
	 *
	 * @deprecated 4.0 Use the "Security.default_message_set" config setting instead
	 *
	 * @param string|array $messageSet
	 */
	public static function set_default_message_set($messageSet)
	{
		Deprecation::notice('4.0', 'Use the "Security.default_message_set" config setting instead');
		self::config()->default_message_set = $messageSet;
	}

	/**
	 * Set strict path checking
	 *
	 * This prevents sharing of the session across several sites in the
	 * domain.
	 *
	 * @deprecated 4.0 Use the "Security.strict_path_checking" config setting instead
	 *
	 * @param boolean $strictPathChecking To enable or disable strict patch
	 *                                    checking.
	 */
	public static function setStrictPathChecking($strictPathChecking)
	{
		Deprecation::notice('4.0', 'Use the "Security.strict_path_checking" config setting instead');
		self::config()->strict_path_checking = $strictPathChecking;
	}


	/**
	 * Get strict path checking
	 *
	 * @deprecated 4.0 Use the "Security.strict_path_checking" config setting instead
	 * @return boolean Status of strict path checking
	 */
	public static function getStrictPathChecking()
	{
		Deprecation::notice('4.0', 'Use the "Security.strict_path_checking" config setting instead');

		return Config::inst()->get('Security', 'strict_path_checking');
	}

}
