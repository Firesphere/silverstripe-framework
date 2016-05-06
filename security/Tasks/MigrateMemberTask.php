<?php

/**
 * Class MigrateMemberTask
 *
 * Migrate all sensitive data related to members to
 */
class MigrateMemberTask extends BuildTask
{
	private static $migrateFields = array(
		'TempIDHash',
		'TempIDExpired',
		'Password',
		'AutoLoginHash',
		'AutoLoginExpired',
		'PasswordEncryption',
		'Salt',
		'PasswordExpiry',
		'LockedOutUntil',
		'FailedLoginCount',
	);

	public function __construct()
	{
		parent::__construct();
		$this->title = 'Member migration to SilverStripe 4';
		$this->description = 'Migrate Members to the new Security implementation<br />It will update all members to the SilverStripe 4 Security implementation';
	}

	/**
	 * Non-destructive migration for now. In the future, this will go deadly.
	 *
	 * @param $request
	 */
	public function run($request)
	{
		/** @var DataList|Member[] $unMigrated */
		$unMigrated = Member::get()->filter(array('MemberSecurityID' => 0));

		foreach ($unMigrated as $member) {
			/** @var MemberSecurity $security */
			$security = MemberSecurity::create();
			foreach (self::$migrateFields as $field) {
				if($member->{$field}) {
					$security->{$field} = $member->{$field};
				}
			}
			$id = $security->write();
			$member->MemberSecurityID = $id;
			$member->write();
			$security->write();
			unset($security);
		}
		echo 'Members migrated: ' . $unMigrated->count() . "\n";
	}


}
