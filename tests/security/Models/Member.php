<?php

use SilverStripe\Security\Security;
use SilverStripe\Security\MemberValidator;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\Model\FieldType\DBDatetime;

/**
 * The member class which represents the users of the system
 *
 * @package framework
 * @subpackage security
 * @property string $FirstName
 * @property string $Surname
 * @property string $Email
 * @property string $Locale
 * @property string $DateFormat
 * @property string $TimeFormat
 * @property int $MemberSecurityID
 * @method MemberSecurity MemberSecurity()
 * @method DataList|MemberPassword[] LoggedPasswords()
 * @method DataList|RememberLoginHash[] RememberLoginHashes()
 */
class Member extends DataObject implements TemplateGlobalProvider
{

	/**
	 * @var array
	 */
	private static $db = array(
		'FirstName'          => 'Varchar',
		'Surname'            => 'Varchar',
		'Email'              => 'Varchar(254)', // See RFC 5321, Section 4.5.3.1.3. (256 minus the < and > character)
		'Locale'             => 'Varchar(6)',
		// In ISO format
		'DateFormat'         => 'Varchar(30)',
		'TimeFormat'         => 'Varchar(30)',
	);

	/**
	 * @var array
	 */
	private static $belongs_many_many = array(
		'Groups' => 'Group',
	);

	/**
	 * @var array
	 */
	private static $has_one = array(
		'MemberSecurity' => 'MemberSecurity'
	);

	/**
	 * @var array
	 */
	private static $has_many = array(
		'LoggedPasswords'     => 'MemberPassword',
		'RememberLoginHashes' => 'RememberLoginHash'
	);

	/**
	 * @var string
	 */
	private static $default_sort = '"Surname", "FirstName"';

	/**
	 * @var array
	 */
	private static $indexes = array(
		'Email' => true,
	);

	/**
	 * @config
	 * @var boolean
	 */
	private static $notify_password_change = true;

	/**
	 * All searchable database columns
	 * in this object, currently queried
	 * with a "column LIKE '%keywords%'
	 * statement.
	 *
	 * @var array
	 * @todo Generic implementation of $searchable_fields on DataObject,
	 * with definition for different searching algorithms
	 * (LIKE, FULLTEXT) and default FormFields to construct a searchform.
	 */
	private static $searchable_fields = array(
		'FirstName',
		'Surname',
		'Email',
	);

	/**
	 * @var array
	 */
	private static $summary_fields = array(
		'FirstName',
		'Surname',
		'Email',
	);

	/**
	 * Internal-use only fields
	 *
	 * @config
	 * @var array
	 */
	private static $hidden_fields = array(
		'MemberSecurity',
		'MemberSecurityID'
	);

	/**
	 * @config
	 * @var array See {@link set_title_columns()}
	 */
	private static $title_format;

	/**
	 * The unique field used to identify this member.
	 * By default, it's "Email", but another common
	 * field could be Username.
	 *
	 * @config
	 * @var string
	 */
	private static $unique_identifier_field = 'Email';

	/**
	 * @config
	 * @var String If this is set, then a session cookie with the given name will be set on log-in,
	 * and cleared on logout.
	 */
	private static $login_marker_cookie;

	/**
	 * Indicates that when a {@link Member} logs in, Member:session_regenerate_id()
	 * should be called as a security precaution.
	 *
	 * This doesn't always work, especially if you're trying to set session cookies
	 * across an entire site using the domain parameter to session_set_cookie_params()
	 *
	 * @config
	 * @var boolean
	 */
	private static $session_regenerate_id = true;

	/**
	 * Ensure the locale is set to something sensible by default.
	 */
	public function populateDefaults()
	{
		parent::populateDefaults();
		$this->Locale = i18n::get_closest_translation(i18n::get_locale());
	}

	/**
	 * Require default records. Check if the Default Admin is set (if needed)
	 * @inheritdoc
	 */
	public function requireDefaultRecords()
	{
		parent::requireDefaultRecords();
		// Default groups should've been built by Group->requireDefaultRecords() already
		static::default_admin();
	}

	/**
	 * Get the default admin record if it exists, or creates it otherwise if enabled
	 *
	 * @return Member
	 */
	public static function default_admin()
	{
		$identifierField = Config::inst()->get('Security', 'unique_identifier_field');
		// Check if set
		if (!Security::has_default_admin()) {
			return null;
		}

		// Find or create ADMIN group
		singleton('Group')->requireDefaultRecords();
		// @todo rewrite to use the constant for the Code @see Group
		$adminGroup = Permission::get_groups_by_permission('ADMIN')->first();

		// Find member
		/** @var Member $admin */
		$admin = Member::get()
			->filter(array($identifierField => Security::default_admin_username()))
			->first();
		if (!$admin) {
			// 'Password' is not set to avoid creating
			// persistent logins in the database. See Security::setDefaultAdmin().
			// Set identifier field to identify this as the default admin
			// @todo This might break if the identifier-field is not a character-type field
			$admin = Member::create();
			$admin->FirstName = _t('Member.DefaultAdminFirstname', 'Default Admin');
			$admin->{$identifierField} = Security::default_admin_username();
			$admin->write();
		}

		// Ensure this user is in the admin group
		if (!$admin->inGroup($adminGroup)) {
			// Add member to group instead of adding group to member
			// This bypasses the privilege escalation code in MemberGroupset
			$adminGroup
				->DirectMembers()
				->add($admin);
		}

		return $admin;
	}



	/**
	 * Regenerate the session_id.
	 * This wrapper is here to make it easier to disable calls to session_regenerate_id(), should you need to.
	 * They have caused problems in certain
	 * quirky problems (such as using the Windmill 0.3.6 proxy).
	 */
	public static function session_regenerate_id()
	{
		if (!self::config()->get('session_regenerate_id')) {
			return;
		}

		// This can be called via CLI during testing.
		if (Director::is_cli()) {
			return;
		}

		$file = '';
		$line = '';

		// @ is to supress win32 warnings/notices when session wasn't cleaned up properly
		// There's nothing we can do about this, because it's an operating system function!
		if (!headers_sent($file, $line)) {
			@session_regenerate_id(true);
		}
	}

	/**
	 * Logs this member in
	 *
	 * @param bool $remember If set to TRUE, the member will be logged in automatically the next time.
	 */
	public function logIn($remember = false)
	{
		$this->extend('beforeMemberLoggedIn');
		$security = $this->MemberSecurity();

		self::session_regenerate_id();

		Session::set('loggedInAs', $this->ID);
		// This lets apache rules detect whether the user has logged in
		if (Member::config()->get('login_marker_cookie')) {
			Cookie::set(Member::config()->get('login_marker_cookie'), 1, 0);
		}

		// Cleans up any potential previous hash for this member on this device
		if ($alcDevice = Cookie::get('alc_device')) {
			RememberLoginHash::get()->filter('DeviceID', $alcDevice)->removeAll();
		}
		if ($remember) {
			$rememberLoginHash = RememberLoginHash::generate($this);
			$tokenExpiryDays = Config::inst()->get('RememberLoginHash', 'token_expiry_days');
			$deviceExpiryDays = Config::inst()->get('RememberLoginHash', 'device_expiry_days');
			Cookie::set('alc_enc', $this->ID . ':' . $rememberLoginHash->getToken(),
				$tokenExpiryDays, null, null, null, true);
			Cookie::set('alc_device', $rememberLoginHash->DeviceID, $deviceExpiryDays, null, null, null, true);
		} else {
			Cookie::set('alc_enc', null);
			Cookie::set('alc_device', null);
			Cookie::force_expiry('alc_enc');
			Cookie::force_expiry('alc_device');
		}

		// Clear the incorrect log-in count
		$this->registerSuccessfulLogin();

		// Don't set column if its not built yet (the login might be precursor to a /dev/build...)
		if (array_key_exists('LockedOutUntil', DB::field_list('MemberSecurity'))) {
			$security->LockedOutUntil = null;
		}

		$this->regenerateTempID();
		$security->write();
		$this->write();

		// Audit logging hook
		$this->extend('memberLoggedIn');
	}

	/**
	 * Trigger regeneration of TempID.
	 *
	 * This should be performed any time the user presents their normal identification (normally Email)
	 * and is successfully authenticated.
	 */
	public function regenerateTempID()
	{
		$generator = new RandomGenerator();
		$security = $this->MemberSecurity();
		$security->TempIDToken = $generator->randomToken('sha1');
		$security->TempIDExpired = self::config()->temp_id_lifetime
			? date('Y-m-d H:i:s', strtotime(DBDatetime::now()->getValue()) + self::config()->temp_id_lifetime)
			: null;
		$security->write();
	}

	/**
	 * Check if the member ID logged in session actually
	 * has a database record of the same ID. If there is
	 * no logged in user, FALSE is returned anyway.
	 *
	 * @return boolean TRUE record found FALSE no record found
	 */
	public static function logged_in_session_exists()
	{
		if ($id = Member::currentUserID()) {
			if ($member = DataObject::get_by_id('Member', $id)) {
				if ($member->exists()) return true;
			}
		}

		return false;
	}

	/**
	 * Log the user in if the "remember login" cookie is set
	 *
	 * The <i>remember login token</i> will be changed on every successful
	 * auto-login.
	 */
	public static function autoLogin()
	{
		// Don't bother trying this multiple times
		if (!class_exists('SapphireTest', false) || !SapphireTest::is_running_test()) {
			self::$_already_tried_to_auto_log_in = true;
		}

		if (strpos(Cookie::get('alc_enc'), ':') === false
			|| Session::get("loggedInAs")
			|| !Security::database_is_ready()
		) {
			return;
		}

		if (strpos(Cookie::get('alc_enc'), ':') && Cookie::get('alc_device') && !Session::get("loggedInAs")) {
			list($uid, $token) = explode(':', Cookie::get('alc_enc'), 2);
			$deviceID = Cookie::get('alc_device');

			$member = Member::get()->byId($uid);

			$rememberLoginHash = null;

			// check if autologin token matches
			if ($member) {
				$hash = $member->MemberSecurity()->encryptWithUserSettings($token);
				$rememberLoginHash = RememberLoginHash::get()
					->filter(array(
						'MemberID' => $member->ID,
						'DeviceID' => $deviceID,
						'Hash'     => $hash
					))->First();
				if (!$rememberLoginHash) {
					$member = null;
				} else {
					// Check for expired token
					$expiryDate = new DateTime($rememberLoginHash->ExpiryDate);
					$now = SS_Datetime::now();
					$now = new DateTime($now->Rfc2822());
					if ($now > $expiryDate) {
						$member = null;
					}
				}
			}

			if ($member) {
				self::session_regenerate_id();
				Session::set("loggedInAs", $member->ID);
				// This lets apache rules detect whether the user has logged in
				if (Member::config()->login_marker_cookie) {
					Cookie::set(Member::config()->login_marker_cookie, 1, 0, null, null, false, true);
				}

				if ($rememberLoginHash) {
					$rememberLoginHash->renew();
					$tokenExpiryDays = Config::inst()->get('RememberLoginHash', 'token_expiry_days');
					Cookie::set('alc_enc', $member->ID . ':' . $rememberLoginHash->getToken(),
						$tokenExpiryDays, null, null, false, true);
				}

				$member->write();

				// Audit logging hook
				$member->extend('memberAutoLoggedIn');
			}
		}
	}

	/**
	 * Logs this member out.
	 */
	public function logOut()
	{
		$this->extend('beforeMemberLoggedOut');

		Session::clear("loggedInAs");
		if (Member::config()->get('login_marker_cookie')) {
			Cookie::set(Member::config()->get('login_marker_cookie'), null, 0);
		}

		Session::destroy(true);

		$this->extend('memberLoggedOut');

		// Clears any potential previous hashes for this member
		RememberLoginHash::clear($this, Cookie::get('alc_device'));

		Cookie::set('alc_enc', null); // // Clear the Remember Me cookie
		Cookie::force_expiry('alc_enc');
		Cookie::set('alc_device', null);
		Cookie::force_expiry('alc_device');

		// Switch back to live in order to avoid infinite loops when
		// redirecting to the login screen (if this login screen is versioned)
		Session::clear('readingMode');

		$this->write();

		// Audit logging hook
		$this->extend('memberLoggedOut');
	}

	/**
	 * Generate an auto login token which can be used to reset the password,
	 * at the same time hashing it and storing in the database.
	 *
	 * @param int $lifetime The lifetime of the auto login hash in days (by default 2 days)
	 *
	 * @returns string Token that should be passed to the client (but NOT persisted).
	 *
	 * @todo Make it possible to handle database errors such as a "duplicate key" error
	 */
	public function generateAutologinTokenAndStoreHash($lifetime = 2)
	{
		$security = $this->MemberSecurity();
		do {
			$generator = new RandomGenerator();
			$token = $generator->randomToken();
			$token = $security->encryptWithUserSettings($token);
		} while (DataObject::get_one('MemberSecurity', array(
			'"AutoLoginHash"' => $token
		)));

		$security->AutoLoginToken = $token;
		$security->AutoLoginExpired = date('Y-m-d H:i:s', time() + (86400 * $lifetime));

		$security->write();

		return $token;
	}

	/**
	 * Find a member record with the given TempIDToken value
	 *
	 * @param string $tempid
	 *
	 * @return Member
	 */
	public static function member_from_tempid($tempid)
	{
		$members = Member::get()
			->filter('MemberSecurity.TempIDToken', $tempid);

		// Exclude expired
		if (MemberSecurity::config()->get('temp_id_lifetime')) {
			$members = $members->filter('TempIDExpired:GreaterThan', DBDatetime::now()->getValue());
		}

		return $members->first();
	}

	/**
	 * Returns the fields for the member form - used in the registration/profile module.
	 * It should return fields that are editable by the admin and the logged-in user.
	 *
	 * @return FieldList Returns a {@link FieldList} containing the fields for
	 *                   the member form.
	 */
	public function getMemberFormFields()
	{
		$fields = parent::getFrontEndFields();

		$fields->replaceField('Password', $password = new ConfirmedPasswordField (
			'Password',
			$this->fieldLabel('Password'),
			null,
			null,
			(bool)$this->ID
		));
		$password->setCanBeEmpty(true);

		$fields->replaceField('Locale', new DropdownField (
			'Locale',
			$this->fieldLabel('Locale'),
			i18n::get_existing_translations()
		));

		$fields->removeByName(static::config()->hidden_fields);
		$fields->removeByName('FailedLoginCount');


		$this->extend('updateMemberFormFields', $fields);

		return $fields;
	}

	/**
	 * Returns the {@link RequiredFields} instance for the Member object. This
	 * Validator is used when saving a {@link CMSProfileController} or added to
	 * any form responsible for saving a users data.
	 *
	 * To customize the required fields, add a {@link DataExtension} to member
	 * calling the `updateValidator()` method.
	 *
	 * @return MemberValidator
	 */
	public function getValidator()
	{
		$validator = Injector::inst()->create('MemberValidator');
		$validator->setForMember($this);
		$this->extend('updateValidator', $validator);

		return $validator;
	}


	/**
	 * Returns the current logged in user
	 *
	 * @return Member|null
	 */
	public static function currentUser()
	{
		$id = Member::currentUserID();

		if ($id) {
			return Member::get()->byID($id);
		}
	}

	/**
	 * Get the ID of the current logged in user
	 *
	 * @return int Returns the ID of the current logged in user or 0.
	 */
	public static function currentUserID()
	{
		$id = Session::get("loggedInAs");
		if (!$id && !self::$_already_tried_to_auto_log_in) {
			self::autoLogin();
			$id = Session::get("loggedInAs");
		}

		return is_numeric($id) ? $id : 0;
	}

	private static $_already_tried_to_auto_log_in = false;


	/*
	 * Generate a random password, with randomiser to kick in if there's no words file on the
	 * filesystem.
	 *
	 * @return string Returns a random password.
	 */
	public static function create_new_password()
	{
		$words = Config::inst()->get('Security', 'word_list');

		if ($words && file_exists($words)) {
			$words = file($words);

			list($usec, $sec) = explode(' ', microtime());
			mt_srand($sec + ((float)$usec * 100000));

			$word = trim($words[mt_rand(0, sizeof($words) - 1)]);
			$number = mt_rand(10, 999);

			return $word . $number;
		} else {
			$random = mt_rand();
			$string = md5($random);
			$output = substr($string, 0, 6);

			return $output;
		}
	}

	/**
	 * Event handler called before writing to the database.
	 */
	public function onBeforeWrite()
	{
		if($this->MemberSecurity()->exists() === false) {
			$security = MemberSecurity::create();
			$id = $security->write();
			$this->MemberSecurityID = $id;
		}

		// If a member with the same "unique identifier" already exists with a different ID, don't allow merging.
		// Note: This does not a full replacement for safeguards in the controller layer (e.g. in a registration form),
		// but rather a last line of defense against data inconsistencies.
		$identifierField = Member::config()->unique_identifier_field;
		if ($this->$identifierField) {

			// Note: Same logic as MemberValidator class
			$filter = array("\"$identifierField\"" => $this->$identifierField);
			if ($this->ID) {
				$filter[] = array('"Member"."ID" <> ?' => $this->ID);
			}
			$existingRecord = DataObject::get_one('Member', $filter);

			if ($existingRecord) {
				throw ValidationException(ValidationResult::create(false, _t(
					'Member.ValidationIdentifierFailed',
					'Can\'t overwrite existing member #{id} with identical identifier ({name} = {value}))',
					'Values in brackets show "fieldname = value", usually denoting an existing email address',
					array(
						'id'    => $existingRecord->ID,
						'name'  => $identifierField,
						'value' => $this->$identifierField
					)
				)));
			}
		}

		// We don't send emails out on dev/tests sites to prevent accidentally spamming users.
		// However, if TestMailer is in use this isn't a risk.
		if (
			array_key_exists('Password', $this->record)
			&& $this->isChanged('Password')
			&& static::config()->get('notify_password_change')
			&& (Director::isLive() || Email::mailer() instanceof TestMailer)
		) {
			/** @var Email $email */
			$email = Email::create();
			$email->setSubject(_t('Member.SUBJECTPASSWORDCHANGED', 'Your password has been changed', 'Email subject'));
			$email->setTemplate('ChangePasswordEmail');
			$email->populateTemplate($this);
			$email->setTo($this->Email);
			$email->send();
		}

		// The test on $this->ID is used for when records are initially created.
		// Note that this only works with cleartext passwords, as we can't rehash
		// existing passwords.
		if ((!$this->ID && $this->Password) || $this->isChanged('Password')) {
			$this->MemberSecurity()->updatePassword($this->Password);
		}

		// save locale
		if (!$this->Locale) {
			$this->Locale = i18n::get_locale();
		}

		parent::onBeforeWrite();
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();

		Permission::flush_permission_cache();

		if ($this->isChanged('Password')) {
			MemberPassword::log($this);
		}
	}

	public function onAfterDelete()
	{
		parent::onAfterDelete();

		//prevent orphaned records remaining in the DB
		$this->deletePasswordLogs();
	}

	/**
	 * Delete the MemberPassword objects that are associated to this user
	 *
	 * @return self
	 */
	protected function deletePasswordLogs()
	{
		foreach ($this->LoggedPasswords() as $password) {
			$password->delete();
			$password->destroy();
		}

		return $this;
	}

	/**
	 * Filter out admin groups to avoid privilege escalation,
	 * If any admin groups are requested, deny the whole save operation.
	 *
	 * @param array $ids Database IDs of Group records
	 *
	 * @return boolean True if the change can be accepted
	 */
	public function onChangeGroups($ids)
	{
		// unless the current user is an admin already OR the logged in user is an admin
		if (Permission::check('ADMIN') || Permission::checkMember($this, 'ADMIN')) {
			return true;
		}

		// If there are no admin groups in this set then it's ok
		$adminGroups = Permission::get_groups_by_permission('ADMIN');
		$adminGroupIDs = ($adminGroups) ? $adminGroups->column('ID') : array();

		return count(array_intersect($ids, $adminGroupIDs)) == 0;
	}


	/**
	 * Check if the member is in one of the given groups.
	 *
	 * @param array|SS_List $groups Collection of {@link Group} DataObjects to check
	 * @param boolean $strict Only determine direct group membership if set to true (Default: false)
	 *
	 * @return bool Returns TRUE if the member is in one of the given groups, otherwise FALSE.
	 */
	public function inGroups($groups, $strict = false)
	{
		if ($groups) {
			foreach ($groups as $group) {
				if ($this->inGroup($group, $strict)) {
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Check if the member is in the given group or any parent groups.
	 *
	 * @param int|Group|string $group Group instance, Group Code or ID
	 * @param boolean $strict Only determine direct group membership if set to TRUE (Default: FALSE)
	 *
	 * @return bool Returns TRUE if the member is in the given group, otherwise FALSE.
	 */
	public function inGroup($group, $strict = false)
	{
		$groupCheckObj = null;
		if (is_numeric($group)) {
			$groupCheckObj = DataObject::get_by_id('Group', $group);
		} elseif (is_string($group)) {
			$groupCheckObj = DataObject::get_one('Group', array(
				'"Group"."Code"' => $group
			));
		} elseif ($group instanceof Group) {
			$groupCheckObj = $group;
		} else {
			user_error('Member::inGroup(): Wrong format for $group parameter', E_USER_ERROR);
		}

		if (null === $groupCheckObj) {
			return false;
		}

		$groupCandidateObjs = ($strict) ? $this->getManyManyComponents("Groups") : $this->Groups();
		if ($groupCandidateObjs) {
			foreach ($groupCandidateObjs as $groupCandidateObj) {
				if ($groupCandidateObj->ID === $groupCheckObj->ID) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Adds the member to a group. This will create the group if the given
	 * group code does not return a valid group object.
	 *
	 * @param string $groupcode
	 * @param string $title Title of the group
	 */
	public function addToGroupByCode($groupcode, $title = '')
	{
		$group = DataObject::get_one('Group', array(
			'"Group"."Code"' => $groupcode
		));

		if ($group) {
			$this->Groups()->add($group);
		} else {
			if (!$title) $title = $groupcode;

			$group = new Group();
			$group->Code = $groupcode;
			$group->Title = $title;
			$group->write();

			$this->Groups()->add($group);
		}
	}

	/**
	 * Removes a member from a group.
	 *
	 * @param string $groupcode
	 */
	public function removeFromGroupByCode($groupcode)
	{
		$group = Group::get()->filter(array('Code' => $groupcode))->first();

		if ($group) {
			$this->Groups()->remove($group);
		}
	}

	/**
	 * @param Array $columns Column names on the Member record to show in {@link getTitle()}.
	 * @param String $sep Separator
	 */
	public static function set_title_columns($columns, $sep = ' ')
	{
		if (!is_array($columns)) {
			$columns = array($columns);
		}
		self::config()->title_format = array('columns' => $columns, 'sep' => $sep);
	}

	//------------------- HELPER METHODS -----------------------------------//

	/**
	 * Get the complete name of the member, by default in the format "<Surname>, <FirstName>".
	 * Falls back to showing either field on its own.
	 *
	 * You can overload this getter with {@link set_title_format()}
	 * and {@link set_title_sql()}.
	 *
	 * @return string Returns the first- and surname of the member. If the ID
	 *  of the member is equal 0, only the surname is returned.
	 */
	public function getTitle()
	{
		$format = $this->config()->title_format;
		if ($format) {
			$values = array();
			foreach ($format['columns'] as $col) {
				$values[] = $this->getField($col);
			}

			return implode($format['sep'], $values);
		}
		if ($this->getField('ID') === 0){
			return $this->getField('Surname');
		}
		else {
			if ($this->getField('Surname') && $this->getField('FirstName')) {
				return $this->getField('Surname') . ', ' . $this->getField('FirstName');
			} elseif ($this->getField('Surname')) {
				return $this->getField('Surname');
			} elseif ($this->getField('FirstName')) {
				return $this->getField('FirstName');
			} else {
				return null;
			}
		}
	}

	/**
	 * Return a SQL CONCAT() fragment suitable for a SELECT statement.
	 * Useful for custom queries which assume a certain member title format.
	 *
	 * @param String $tableName
	 *
	 * @return String SQL
	 */
	public static function get_title_sql($tableName = 'Member')
	{
		// This should be abstracted to SSDatabase concatOperator or similar.
		$op = (DB::get_conn() instanceof MSSQLDatabase) ? " + " : " || ";

		$format = self::config()->title_format;
		if ($format) {
			$columnsWithTablename = array();
			foreach ($format['columns'] as $column) {
				$columnsWithTablename[] = "\"$tableName\".\"$column\"";
			}

			return "(" . implode(" $op '" . $format['sep'] . "' $op ", $columnsWithTablename) . ")";
		} else {
			return "(\"$tableName\".\"Surname\" $op ' ' $op \"$tableName\".\"FirstName\")";
		}
	}


	/**
	 * Get the complete name of the member
	 *
	 * @return string Returns the first- and surname of the member.
	 */
	public function getName()
	{
		return ($this->Surname) ? trim($this->FirstName . ' ' . $this->Surname) : $this->FirstName;
	}


	/**
	 * Set first- and surname
	 *
	 * This method assumes that the last part of the name is the surname, e.g.
	 * <i>A B C</i> will result in firstname <i>A B</i> and surname <i>C</i>
	 *
	 * @param string $name The name
	 */
	public function setName($name)
	{
		$nameParts = explode(' ', $name);
		$this->Surname = array_pop($nameParts);
		$this->FirstName = implode(' ', $nameParts);
	}


	/**
	 * Alias for {@link setName}
	 *
	 * @param string $name The name
	 *
	 * @see setName()
	 */
	public function splitName($name)
	{
		$this->setName($name);
	}

	/**
	 * Override the default getter for DateFormat so the
	 * default format for the user's locale is used
	 * if the user has not defined their own.
	 *
	 * @return string ISO date format
	 */
	public function getDateFormat()
	{
		if ($this->getField('DateFormat')) {
			return $this->getField('DateFormat');
		} else {
			return Config::inst()->get('i18n', 'date_format');
		}
	}

	/**
	 * Override the default getter for TimeFormat so the
	 * default format for the user's locale is used
	 * if the user has not defined their own.
	 *
	 * @return string ISO date format
	 */
	public function getTimeFormat()
	{
		if ($this->getField('TimeFormat')) {
			return $this->getField('TimeFormat');
		} else {
			return Config::inst()->get('i18n', 'time_format');
		}
	}

	//---------------------------------------------------------------------//


	/**
	 * Get a "many-to-many" map that holds for all members their group memberships,
	 * including any parent groups where membership is implied.
	 * Use {@link DirectGroups()} to only retrieve the group relations without inheritance.
	 *
	 * @todo Push all this logic into MemberGroupset's getIterator()?
	 * @return MemberGroupset
	 */
	public function Groups()
	{
		/** @var MemberGroupset $groups */
		$groups = MemberGroupset::create('Group', 'Group_Members', 'GroupID', 'MemberID');
		$groups = $groups->forForeignID($this->ID);

		$this->extend('updateGroups', $groups);

		return $groups;
	}

	/**
	 * @return ManyManyList
	 */
	public function DirectGroups()
	{
		return $this->getManyManyComponents('Groups');
	}

	/**
	 * Get a member SQLMap of members in specific groups
	 *
	 * If no $groups is passed, all members will be returned
	 *
	 * @param mixed $groups - takes a SS_List, an array or a single Group.ID
	 *
	 * @return SS_Map Returns an SS_Map that returns all Member data.
	 */
	public static function map_in_groups($groups = null)
	{
		$groupIDList = array();

		if ($groups instanceof SS_List) {
			foreach ($groups as $group) {
				$groupIDList[] = $group->ID;
			}
		} elseif (is_array($groups)) {
			$groupIDList = $groups;
		} elseif ($groups) {
			$groupIDList[] = $groups;
		}

		// No groups, return all Members
		if (!$groupIDList) {
			return Member::get()->sort(array('Surname' => 'ASC', 'FirstName' => 'ASC'))->map();
		}

		$membersList = new ArrayList();
		// This is a bit ineffective, but follow the ORM style
		/** @var Group $group */
		foreach (Group::get()->byIDs($groupIDList) as $group) {
			$membersList->merge($group->Members());
		}

		$membersList->removeDuplicates('ID');

		return $membersList->map();
	}


	/**
	 * Get a map of all members in the groups given that have CMS permissions
	 *
	 * If no groups are passed, all groups with CMS permissions will be used.
	 *
	 * @param ArrayList|array $groups Groups to consider or NULL to use all groups with
	 *                      CMS permissions.
	 *
	 * @return SS_Map Returns a map of all members in the groups given that
	 *                have CMS permissions.
	 */
	public static function mapInCMSGroups($groups = null)
	{
		if ($groups === null || count($groups) === 0 || $groups->count() === 0) {
			$perms = array('ADMIN', 'CMS_ACCESS_AssetAdmin');

			if (class_exists('CMSMain')) {
				$cmsPerms = singleton('CMSMain')->providePermissions();
			} else {
				$cmsPerms = singleton('LeftAndMain')->providePermissions();
			}

			if (!empty($cmsPerms)) {
				$perms = array_unique(array_merge($perms, array_keys($cmsPerms)));
			}

			$permsClause = DB::placeholders($perms);
			$groups = DataObject::get('Group')
				->innerJoin("Permission", '"Permission"."GroupID" = "Group"."ID"')
				->where(array(
					"\"Permission\".\"Code\" IN ($permsClause)" => $perms
				));
		}

		$groupIDList = array();

		if (is_a($groups, 'SS_List')) {
			foreach ($groups as $group) {
				$groupIDList[] = $group->ID;
			}
		} elseif (is_array($groups)) {
			$groupIDList = $groups;
		}

		$members = Member::get()
			->innerJoin("Group_Members", '"Group_Members"."MemberID" = "Member"."ID"')
			->innerJoin("Group", '"Group"."ID" = "Group_Members"."GroupID"');
		if ($groupIDList) {
			$groupClause = DB::placeholders($groupIDList);
			$members = $members->where(array(
				"\"Group\".\"ID\" IN ($groupClause)" => $groupIDList
			));
		}

		return $members->sort('"Member"."Surname", "Member"."FirstName"')->map();
	}


	/**
	 * Get the groups in which the member is NOT in
	 *
	 * When passed an array of groups, and a component set of groups, this
	 * function will return the array of groups the member is NOT in.
	 *
	 * @param array $groupList An array of group code names.
	 * @param array|MemberGroupset $memberGroups A component set of groups (if set to NULL,
	 *                            $this->groups() will be used)
	 *
	 * @return array Groups in which the member is NOT in.
	 */
	public function memberNotInGroups($groupList, $memberGroups = null)
	{
		if (!$memberGroups) {
			$memberGroups = $this->Groups();
		}

		foreach ($memberGroups as $group) {
			if (in_array($group->Code, $groupList, null)) {
				$index = array_search($group->Code, $groupList, null);
				unset($groupList[$index]);
			}
		}

		return $groupList;
	}


	/**
	 * Return a {@link FieldList} of fields that would appropriate for editing
	 * this member.
	 *
	 * @return FieldList Return a FieldList of fields that would appropriate for
	 *                   editing this member.
	 */
	public function getCMSFields()
	{
		require_once BASE_PATH . '/' . THIRDPARTY_DIR . '/Zend/Date.php';

		$self = $this;
		$this->beforeUpdateCMSFields(function ($fields) use ($self) {
			$mainFields = $fields->fieldByName("Root")->fieldByName("Main")->Children;

			$password = new ConfirmedPasswordField(
				'Password',
				null,
				null,
				null,
				true // showOnClick
			);
			$password->setCanBeEmpty(true);
			if (!$self->ID) {
				$password->showOnClick = false;
			}
			$fields->addFieldToTab("Root.Main",  $password);

			$mainFields->replaceField('Locale', new DropdownField(
				"Locale",
				_t('Member.INTERFACELANG', "Interface Language", 'Language of the CMS'),
				i18n::get_existing_translations()
			));
			$mainFields->removeByName($self->config()->hidden_fields);

			if (!MemberSecurity::config()->get('lock_out_after_incorrect_logins')) {
				$mainFields->removeByName('FailedLoginCount');
			}


			// Groups relation will get us into logical conflicts because
			// Members are displayed within  group edit form in SecurityAdmin
			$fields->removeByName('Groups');

			// Members shouldn't be able to directly view/edit logged passwords
			$fields->removeByName('LoggedPasswords');

			$fields->removeByName('RememberLoginHashes');

			if (Permission::check('EDIT_PERMISSIONS')) {
				$groupsMap = array();
				foreach (Group::get() as $group) {
					// Listboxfield values are escaped, use ASCII char instead of &raquo;
					$groupsMap[$group->ID] = $group->getBreadcrumbs(' > ');
				}
				asort($groupsMap);
				$fields->addFieldToTab('Root.Main',
					ListboxField::create('DirectGroups', singleton('Group')->i18n_plural_name())
						->setSource($groupsMap)
						->setAttribute(
							'data-placeholder',
							_t('Member.ADDGROUP', 'Add group', 'Placeholder text for a dropdown')
						)
				);


				// Add permission field (readonly to avoid complicated group assignment logic).
				// This should only be available for existing records, as new records start
				// with no permissions until they have a group assignment anyway.
				if ($self->ID) {
					$permissionsField = new PermissionCheckboxSetField_Readonly(
						'Permissions',
						false,
						'Permission',
						'GroupID',
						// we don't want parent relationships, they're automatically resolved in the field
						$self->getManyManyComponents('Groups')
					);
					$fields->findOrMakeTab('Root.Permissions', singleton('Permission')->i18n_plural_name());
					$fields->addFieldToTab('Root.Permissions', $permissionsField);
				}
			}

			$permissionsTab = $fields->fieldByName("Root")->fieldByName('Permissions');
			if ($permissionsTab) {
				$permissionsTab->addExtraClass('readonly');
			}

			$defaultDateFormat = Zend_Locale_Format::getDateFormat(new Zend_Locale($self->Locale));
			$dateFormatMap = array(
				'MMM d, yyyy' => Zend_Date::now()->toString('MMM d, yyyy'),
				'yyyy/MM/dd'  => Zend_Date::now()->toString('yyyy/MM/dd'),
				'MM/dd/yyyy'  => Zend_Date::now()->toString('MM/dd/yyyy'),
				'dd/MM/yyyy'  => Zend_Date::now()->toString('dd/MM/yyyy'),
			);
			$dateFormatMap[$defaultDateFormat] = Zend_Date::now()->toString($defaultDateFormat)
				. sprintf(' (%s)', _t('Member.DefaultDateTime', 'default'));
			$mainFields->push(
				$dateFormatField = new MemberDatetimeOptionsetField(
					'DateFormat',
					$self->fieldLabel('DateFormat'),
					$dateFormatMap
				)
			);
			$dateFormatField->setValue($self->DateFormat);

			$defaultTimeFormat = Zend_Locale_Format::getTimeFormat(new Zend_Locale($self->Locale));
			$timeFormatMap = array(
				'h:mm a' => Zend_Date::now()->toString('h:mm a'),
				'H:mm'   => Zend_Date::now()->toString('H:mm'),
			);
			$timeFormatMap[$defaultTimeFormat] = Zend_Date::now()->toString($defaultTimeFormat)
				. sprintf(' (%s)', _t('Member.DefaultDateTime', 'default'));
			$mainFields->push(
				$timeFormatField = new MemberDatetimeOptionsetField(
					'TimeFormat',
					$self->fieldLabel('TimeFormat'),
					$timeFormatMap
				)
			);
			$timeFormatField->setValue($self->TimeFormat);
		});

		return parent::getCMSFields();
	}

	/**
	 *
	 * @param boolean $includerelations a boolean value to indicate if the labels returned include relation fields
	 *
	 * @return array|string
	 */
	public function fieldLabels($includerelations = true)
	{
		$labels = parent::fieldLabels($includerelations);

		$labels['FirstName'] = _t('Member.FIRSTNAME', 'First Name');
		$labels['Surname'] = _t('Member.SURNAME', 'Surname');
		$labels['Email'] = _t('Member.EMAIL', 'Email');
		$labels['Password'] = _t('Member.db_Password', 'Password');
		$labels['PasswordExpiry'] = _t('Member.db_PasswordExpiry', 'Password Expiry Date', 'Password expiry date');
		$labels['LockedOutUntil'] = _t('Member.db_LockedOutUntil', 'Locked out until', 'Security related date');
		$labels['Locale'] = _t('Member.db_Locale', 'Interface Locale');
		$labels['DateFormat'] = _t('Member.DATEFORMAT', 'Date format');
		$labels['TimeFormat'] = _t('Member.TIMEFORMAT', 'Time format');
		if ($includerelations) {
			$labels['Groups'] = _t('Member.belongs_many_many_Groups', 'Groups',
				'Security Groups this member belongs to');
		}

		return $labels;
	}

	public function isLoggedIn()
	{
		$security = $this->MemberSecurity();
		return ($security->isLockedOut() === false
			&& MemberSecurity::member_from_autologintoken($this->MemberSecurity()->AutoLoginToken) instanceof Member);
	}

	/**
	 * Users can view their own record.
	 * Otherwise they'll need ADMIN or CMS_ACCESS_SecurityAdmin permissions.
	 * This is likely to be customized for social sites etc. with a looser permission model.
	 */
	public function canView($member = null)
	{
		//get member
		if (!($member instanceof Member)) {
			$member = Member::currentUser();
		}
		//check for extensions, we do this first as they can overrule everything
		$extended = $this->extendedCan(__FUNCTION__, $member);
		if ($extended !== null) {
			return $extended;
		}

		//need to be logged in and/or most checks below rely on $member being a Member
		if ($member === null) {
			return false;
		}
		// members can usually view their own record
		if ($this->ID === $member->ID) {
			return true;
		}

		//standard check
		return Permission::checkMember($member, 'CMS_ACCESS_SecurityAdmin');
	}

	/**
	 * Users can edit their own record.
	 * Otherwise they'll need ADMIN or CMS_ACCESS_SecurityAdmin permissions
	 */
	public function canEdit($member = null)
	{
		//get member
		if (!($member instanceof Member)) {
			$member = Member::currentUser();
		}
		//check for extensions, we do this first as they can overrule everything
		$extended = $this->extendedCan(__FUNCTION__, $member);
		if ($extended !== null) {
			return $extended;
		}

		//need to be logged in and/or most checks below rely on $member being a Member
		if (!$member) {
			return false;
		}

		// HACK: we should not allow for an non-Admin to edit an Admin
		if (Permission::checkMember($member, 'ADMIN') === false) {
			return false;
		}
		// members can usually edit their own record
		if ($this->ID === $member->ID) {
			return true;
		}

		//standard check
		return Permission::checkMember($member, 'CMS_ACCESS_SecurityAdmin');
	}

	/**
	 * Users can edit their own record.
	 * Otherwise they'll need ADMIN or CMS_ACCESS_SecurityAdmin permissions
	 */
	public function canDelete($member = null)
	{
		if (!($member instanceof Member)) {
			$member = Member::currentUser();
		}
		//check for extensions, we do this first as they can overrule everything
		$extended = $this->extendedCan(__FUNCTION__, $member);
		if ($extended !== null) {
			return $extended;
		}

		//need to be logged in and/or most checks below rely on $member being a Member
		if (!$member) {
			return false;
		}
		// Members are not allowed to remove themselves,
		// since it would create inconsistencies in the admin UIs.
		if ($this->ID && $member->ID == $this->ID) {
			return false;
		}

		// HACK: if you want to delete a member, you have to be a member yourself.
		// this is a hack because what this should do is to stop a user
		// deleting a member who has more privileges (e.g. a non-Admin deleting an Admin)
		if (Permission::checkMember($this, 'ADMIN')) {
			if (!Permission::checkMember($member, 'ADMIN')) {
				return false;
			}
		}

		//standard check
		return Permission::checkMember($member, 'CMS_ACCESS_SecurityAdmin');
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
		$security = $this->MemberSecurity();
		$valid = $security->validate();

		if ($valid->valid()) {
			$security->AutoLoginToken = null;
			$security->write();
		}

		return $valid;
	}

	/**
	 * Tell this member that someone made a failed attempt at logging in as them.
	 * This can be used to lock the user out temporarily if too many failed attempts are made.
	 */
	public function registerFailedLogin()
	{
		$security = $this->MemberSecurity();
		if (MemberSecurity::config()->get('lock_out_after_incorrect_logins')) {
			// Keep a tally of the number of failed log-ins so that we can lock people out
			++$security->FailedLoginCount;

			if ($security->FailedLoginCount >= MemberSecurity::config()->get('lock_out_after_incorrect_logins')) {
				$lockoutMins = MemberSecurity::config()->get('lock_out_delay_mins');
				$security->LockedOutUntil = date('Y-m-d H:i:s', time() + $lockoutMins * 60);
			}
		}
		$this->extend('registerFailedLogin');
		$security->write();
	}

	/**
	 * Tell this member that a successful login has been made
	 */
	public function registerSuccessfulLogin()
	{
		$security = $this->MemberSecurity();
		if (MemberSecurity::config()->get('lock_out_after_incorrect_logins')) {
			// Forgive all past login failures, we are a generous god.
			$security->FailedLoginCount = 0;
			$security->write();
		}
	}

	/**
	 * Get the HtmlEditorConfig for this user to be used in the CMS.
	 * This is set by the group. If multiple configurations are set,
	 * the one with the highest priority wins.
	 *
	 * @return string
	 */
	public function getHtmlEditorConfigForCMS()
	{
		$currentName = '';
		$currentPriority = 0;

		foreach ($this->Groups() as $group) {
			$configName = $group->HtmlEditorConfig;
			if ($configName) {
				$config = HtmlEditorConfig::get($group->HtmlEditorConfig);
				if ($config && $config->getOption('priority') > $currentPriority) {
					$currentName = $configName;
					$currentPriority = $config->getOption('priority');
				}
			}
		}

		// If can't find a suitable editor, just default to cms
		return $currentName ? $currentName : 'cms';
	}

	public static function get_template_global_variables()
	{
		return array(
			'CurrentMember' => 'currentUser',
			'currentUser',
		);
	}

	/**
	 * Here be deprecations
	 */

	/**
	 * @deprecated 4.0 Use the "Member.session_regenerate_id" config setting instead
	 */
	public static function set_session_regenerate_id($bool)
	{
		Deprecation::notice('4.0', 'Use the "Member.session_regenerate_id" config setting instead');
		self::config()->session_regenerate_id = $bool;
	}


	/**
	 * If this is called, then a session cookie will be set to "1" whenever a user
	 * logs in.  This lets 3rd party tools, such as apache's mod_rewrite, detect
	 * whether a user is logged in or not and alter behaviour accordingly.
	 *
	 * One known use of this is to bypass static caching for logged in users.  This is
	 * done by putting this into _config.php
	 * <pre>
	 * Member::set_login_marker_cookie("SS_LOGGED_IN");
	 * </pre>
	 *
	 * And then adding this condition to each of the rewrite rules that make use of
	 * the static cache.
	 * <pre>
	 * RewriteCond %{HTTP_COOKIE} !SS_LOGGED_IN=1
	 * </pre>
	 *
	 * @deprecated 4.0 Use the "Member.login_marker_cookie" config setting instead
	 *
	 * @param $cookieName string The name of the cookie to set.
	 */
	public static function set_login_marker_cookie($cookieName)
	{
		Deprecation::notice('4.0', 'Use the "Member.login_marker_cookie" config setting instead');
		self::config()->login_marker_cookie = $cookieName;
	}

	/**
	 * Get the field used for uniquely identifying a member
	 * in the database. {@see Member::$unique_identifier_field}
	 *
	 * @deprecated 4.0 Use the "Member.unique_identifier_field" config setting instead
	 * @return string
	 */
	public static function get_unique_identifier_field()
	{
		Deprecation::notice('4.0', 'Use the "Member.unique_identifier_field" config setting instead');

		return Member::config()->unique_identifier_field;
	}

	/**
	 * Set the field used for uniquely identifying a member
	 * in the database. {@see Member::$unique_identifier_field}
	 *
	 * @deprecated 4.0 Use the "Member.unique_identifier_field" config setting instead
	 *
	 * @param string $field The field name to set as the unique field
	 */
	public static function set_unique_identifier_field($field)
	{
		Deprecation::notice('4.0', 'Use the "Member.unique_identifier_field" config setting instead');
		Member::config()->unique_identifier_field = $field;
	}


	/**
	 * Set the number of days that a password should be valid for.
	 * Set to null (the default) to have passwords never expire.
	 *
	 * @deprecated 4.0 Use the "Member.password_expiry_days" config setting instead
	 */
	public static function set_password_expiry($days)
	{
		Deprecation::notice('4.0', 'Use the "MemberSecurity.password_expiry_days" config setting instead');
		MemberSecurity::config()->password_expiry_days = $days;
	}

	/**
	 * Configure the security system to lock users out after this many incorrect logins
	 *
	 * @deprecated 4.0 Use the "Member.lock_out_after_incorrect_logins" config setting instead
	 */
	public static function lock_out_after_incorrect_logins($numLogins)
	{
		Deprecation::notice('4.0', 'Use the "MemberSecurity.lock_out_after_incorrect_logins" config setting instead');
		MemberSecurity::config()->update('lock_out_after_incorrect_logins',  $numLogins);
	}

}