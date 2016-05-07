<?php
use SilverStripe\Security\PasswordEncryptor;

/**
 * Keep track of users' previous passwords, so that we can check that new passwords aren't changed back to old ones.
 *
 * @package framework
 * @subpackage security
 * @property string $Password
 * @property string $Salt
 * @property string $PasswordEncryption
 * @property int $MemberID
 * @method Member Member()
 */
class MemberPassword extends DataObject
{
	private static $db = array(
		'Password'           => 'Varchar(160)',
		'Salt'               => 'Varchar(50)',
		'PasswordEncryption' => 'Varchar(50)',
	);

	private static $has_one = array(
		'Member' => 'Member'
	);

	/**
	 * Log a password change from the given member.
	 * Call MemberPassword::log($this) from within Member whenever the password is changed.
	 *
	 * @param Member $member
	 */
	public static function log($member)
	{
		$record = MemberPassword::create();
		$security = $member->MemberSecurity();
		$record->MemberID = $member->ID;
		$record->Password = $security->Password;
		$record->PasswordEncryption = $security->PasswordEncryption;
		$record->Salt = $security->Salt;
		$record->write();
	}

	/**
	 * Check if the given password is the same as the one stored in this record.
	 * See {@link Member->checkPassword()}.
	 *
	 * @param String $password Cleartext password
	 *
	 * @return Boolean
	 */
	public function checkPassword($password)
	{
		$e = PasswordEncryptor::create_for_algorithm($this->PasswordEncryption);

		return $e->check($this->Password, $password, $this->Salt, $this->Member());
	}


}
