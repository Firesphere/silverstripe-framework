<?php
use SilverStripe\Security\PasswordEncryptor;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityEncryption;

/**
 * The general idea here, is to move the hashes and security things
 * away from the Member itself.
 *
 * @todo Work in Progress, not yet implemented.
 * 
 * Class MemberSecurity
 * @package framework/security
 * @property string $TempIDToken
 * @property string $TempIDExpired
 * @property string $Password
 * @property string $AutoLoginToken
 * @property string $AutoLoginExpired
 * @property string $PasswordEncryption
 * @property string $Salt
 * @property string $PasswordExpiry
 * @property string $LockedOutUntil
 * @property string $FailedLoginCount
 * @method Member Member()
 */
class MemberSecurity extends DataObject
{

	/**
	 * @config
	 * {@link MemberValidator} object for validating user's password
	 *
	 * @param SilverStripe\Security\MemberValidator
	 */
	private static $password_validator;

	/**
	 * @var array
	 */
	private static $db = array(
		'TempIDToken'        => 'Varchar(160)', // Temporary id used for cms re-authentication
		'TempIDExpired'      => 'SS_Datetime', // Expiry of temp login
		'Password'           => 'Varchar(160)',
		'AutoLoginToken'     => 'Varchar(160)', // Used to auto-login the user on password reset
		'AutoLoginExpired'   => 'SS_Datetime',
		// This is an arbitrary code pointing to a PasswordEncryptor instance,
		// not an actual encryption algorithm.
		// Warning: Never change this field after its the first password hashing without
		// providing a new cleartext password as well.
		'PasswordEncryption' => "Varchar(50)",
		'Salt'               => 'Varchar(50)',
		'PasswordExpiry'     => 'Date',
		'LockedOutUntil'     => 'SS_Datetime',
		// handled in registerFailedLogin(), only used if $lock_out_after_incorrect_logins is set
		'FailedLoginCount'   => 'Int',
	);

	/**
	 * @var array
	 */
	private static $belongs_to = array(
		'Member' => 'Member' // @todo enable when implemented
	);

	private static $indexes = array(
		'Salt'           => 'unique("Salt")',
		'TempIDToken'    => 'unique("TempIDToken")',
		'AutoLoginToken' => 'unique("AutoLoginToken")	'
	);

	private static $hidden_fields = array(
		'TempIDToken',
		'TempIDExpired',
		'AutoLoginToken',
		'AutoLoginExpired',
		'PasswordEncryption',
		'Salt',
		'PasswordExpiry',
		'LockedOutUntil',
		'FailedLoginCount',
	);

	/**
	 * Returns a valid {@link ValidationResult} if this member can currently log in, or an invalid
	 * one with error messages to display if the member is locked out.
	 *
	 * You can hook into this with a "canLogIn" method on an attached extension.
	 *
	 * @return ValidationResult
	 */
	public function canLogIn()
	{
		/** @var ValidationResult $result */
		$result = ValidationResult::create();

		if ($this->isLockedOut()) {
			$result->error(
				_t(
					'Member.ERRORLOCKEDOUT2',
					'Your account has been temporarily disabled because of too many failed attempts at ' .
					'logging in. Please try again in {count} minutes.',
					null,
					array('count' => static::config()->get('lock_out_delay_mins'))
				)
			);
		}

		$this->extend('canLogIn', $result);

		return $result;
	}

	/**
	 * Check if the passed password matches the stored one (if the member is not locked out).
	 *
	 * @param  string $password
	 *
	 * @return ValidationResult
	 */
	public function checkPassword($password)
	{
		$result = $this->canLogIn();

		// Short-circuit the result upon failure, no further checks needed.
		if (!$result->valid()) {
			return $result;
		}

		if (empty($this->Password) && $this->exists()) {
			$result->error(_t('Member.NoPassword', 'There is no password on this member.'));

			return $result;
		}

		try {
			$encryptor = PasswordEncryptor::create_for_algorithm($this->PasswordEncryption);
		} catch (Exception $e) {
			$result->error($e->getMessage());

			return $result;
		}
		if ($encryptor->check($this->Password, $password, $this->Salt, $this) === false) {
			$result->error(_t(
				'Member.ERRORWRONGCRED',
				'The provided details don\'t seem to be correct. Please try again.'
			));
		}

		return $result;
	}

	public function isPasswordExpired()
	{
		if ($this->PasswordExpiry === null) {
			return false;
		}

		return strtotime(date('Y-m-d')) >= strtotime($this->PasswordExpiry);
	}

	/**
	 * Validate this member object.
	 */
	public function validate()
	{
		$valid = parent::validate();

		if (($this->Password && self::$password_validator)
			&& (!$this->ID || $this->isChanged('Password'))
		) {
			$valid->combineAnd(self::$password_validator->validate($this->Password, $this));

		}

		if (((!$this->ID && $this->SetPassword) || $this->isChanged('SetPassword'))
			&& $this->SetPassword && self::$password_validator
		) {
			$valid->combineAnd(self::$password_validator->validate($this->SetPassword, $this));
		}


		return $valid;
	}

	/**
	 * Returns true if this user is locked out
	 */
	public function isLockedOut()
	{
		return $this->LockedOutUntil && time() < strtotime($this->LockedOutUntil);
	}

	/**
	 * Check the token against the member.
	 *
	 * @param string $autologinToken
	 *
	 * @returns bool Is token valid?
	 */
	public function validateAutoLoginToken($autologinToken)
	{
		$hash = $this->encryptWithUserSettings($autologinToken);
		$member = self::member_from_autologintoken($hash, false);

		return (bool)$member;
	}


	/**
	 * Utility for generating secure password hashes for this member.
	 */
	public function encryptWithUserSettings($string)
	{
		if (!$string) {
			return null;
		}

		// If the algorithm or salt is not available, it means we are operating
		// on legacy account with unhashed password. Do not hash the string.
		if (!$this->PasswordEncryption) {
			return $string;
		}

		// We assume we have PasswordEncryption and Salt available here.
		$e = PasswordEncryptor::create_for_algorithm($this->PasswordEncryption);

		return $e->encrypt($string, $this->Salt);

	}


	/**
	 * Return the member for the auto login hash
	 *
	 * @param string $token The hash key
	 * @param bool $login Should the member be logged in?
	 *
	 * @return Member the matching member, if valid
	 * @return Member
	 */
	public static function member_from_autologintoken($token, $login = false)
	{

		$nowExpression = DB::get_conn()->now();
		/** @var MemberSecurity $memberSecurity */
		$memberSecurity = self::get()->filter(array(
			"\"Member\".\"AutoLoginToken\"" => $token,
			"\"Member\".\"AutoLoginExpired\" > $nowExpression", // NOW() can't be parameterised
			"\"MemberID\""                  => Member::currentUserID()
		))->first();

		if ($login && $memberSecurity) $memberSecurity->Member()->logIn();

		return $memberSecurity->Member();
	}

	/**
	 * Set a {@link PasswordValidator} object to use to validate member's passwords.
	 */
	public static function set_password_validator($pv)
	{
		self::$password_validator = $pv;
	}

	/**
	 * Returns the current {@link PasswordValidator}
	 */
	public static function password_validator()
	{
		return self::$password_validator;
	}

	/**
	 * Change password. This will cause rehashing according to
	 * the `PasswordEncryption` property.
	 *
	 * @param String $password Cleartext password
	 *
	 * @return ValidationResult
	 */
	public function changePassword($password)
	{
		$this->Password = $password;
		$valid = $this->validate();

		if ($valid->valid()) {
			$this->AutoLoginHash = null;
			$this->write();
		}

		return $valid;
	}

	public function updatePassword($password)
	{
		try {
			// Password was changed: encrypt the password according the settings
			$encryption_details = SecurityEncryption::encrypt_password(
				$password, // this is assumed to be cleartext
				$this->Salt,
				$this->PasswordEncryption ?: Security::config()->password_encryption_algorithm,
				$this
			);
		} catch (Exception $e) {
			return $e->getMessage();
		}

		// Overwrite the Password property with the hashed value
		$this->Password = $encryption_details['password'];
		$this->Salt = $encryption_details['salt'];
		$this->PasswordEncryption = $encryption_details['algorithm'];

		// If we haven't manually set a password expiry
		if (!$this->isChanged('PasswordExpiry')) {
			// then set it for us
			if (self::config()->get('password_expiry_days')) {
				$this->PasswordExpiry = date('Y-m-d', time() + 86400 * self::config()->get('password_expiry_days'));
			} else {
				$this->PasswordExpiry = null;
			}
		}
		$this->write();
	}


}
