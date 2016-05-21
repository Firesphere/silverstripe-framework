<?php

namespace SilverStripe\Security;

use Config;
use Controller;
use Convert;
use Director;
use FieldList;
use Form;
use FormAction;
use FormField;
use HiddenField;
use HTTP;
use Member;
use MemberSecurity;
use PasswordField;
use Session;
use SS_HTTPResponse;

/**
 * Standard Change Password Form
 *
 * @package framework
 * @subpackage security
 */
class ChangePasswordForm extends Form
{
	use SecurityBase;

	/**
	 * Constructor
	 *
	 * @param Controller $controller The parent controller, necessary to
	 *                               create the appropriate form action tag.
	 * @param string $name The method on the controller that will return this
	 *                     form object.
	 * @param FieldList|FormField $fields All of the fields in the form - a
	 *                                   {@link FieldList} of {@link FormField}
	 *                                   objects.
	 * @param FieldList|FormAction $actions All of the action buttons in the
	 *                                     form - a {@link FieldList} of
	 */
	public function __construct($controller, $name, $fields = null, $actions = null)
	{
		$backURL = $controller->getRequest()->getVar('BackURL');
		if (null === $backURL) {
			$backURL = Session::get('BackURL');
		}

		if (!$fields) {
			/** @var FieldList $fields */
			$fields = FieldList::create();

			// Security/changepassword?h=XXX redirects to Security/changepassword
			// without GET parameter to avoid potential HTTP referer leakage.
			// In this case, a user is not logged in, and no 'old password' should be necessary.
			if (Member::currentUser()) {
				$fields->push(PasswordField::create('OldPassword', _t('Member.YOUROLDPASSWORD', 'Your old password')));
			}

			$fields->push(PasswordField::create('NewPassword1', _t('Member.NEWPASSWORD', 'New Password')));
			$fields->push(PasswordField::create('NewPassword2', _t('Member.CONFIRMNEWPASSWORD', 'Confirm New Password')));
		}
		if (!$actions) {
			$actions = FieldList::create(
				FormAction::create('doChangePassword', _t('Member.BUTTONCHANGEPASSWORD', 'Change Password'))
			);
		}

		if (null !== $backURL) {
			$fields->push(HiddenField::create('BackURL', 'BackURL', $backURL));
		}

		parent::__construct($controller, $name, $fields, $actions);
	}


	/**
	 * Change the password
	 *
	 * @param array $data The user submitted data
	 *
	 * @return SS_HTTPResponse
	 */
	public function doChangePassword(array $data)
	{
		/** Break early if we disallow insecure passwords */
		if (Config::inst()->get('Security', 'block_insecure_passwords') === true) {
			$passwordList = InsecurePasswordList::getPasswords();
			if ($passwordList->find('password', $data['NewPassword1'])) {
				$message = _t('Member.ERRORPASSWORDUNSAVE', 'Your chosen password is on the list of insecure passwords, please try again');
				$type = 'bad';

				return $this->changePasswordError($message, $type);
			}
		}

		/** @var Member $member */
		if ($member = Member::currentUser()) {
			// The user was logged in, check the current password
			if (empty($data['OldPassword']) || !$member->MemberSecurity()->checkPassword($data['OldPassword'])->valid()) {
				$message = _t('Member.ERRORPASSWORDNOTMATCH', 'Your current password does not match, please try again');
				$type = 'bad';

				return $this->changePasswordError($message, $type);
			}
		}

		if ($member === null) {
			if (Session::get('AutoLoginHash')) {
				$member = MemberSecurity::member_from_autologintoken(Session::get('AutoLoginHash'));
			}

			// The user is not logged in and no valid auto login hash is available
			if ($member === null) {
				Session::clear('AutoLoginHash');

				return $this->controller->redirect($this->controller->Link('login'));
			}
		}

		// Check the new password
		if (empty($data['NewPassword1'])) {
			$message = _t('Member.EMPTYNEWPASSWORD', "The new password can't be empty, please try again");
			$type = 'bad';
			$this->changePasswordError($message, $type);

		} elseif ($data['NewPassword1'] === $data['NewPassword2']) {
			$isValid = $member->MemberSecurity()->changePassword($data['NewPassword1']);
			if ($isValid->valid()) {
				$member->logIn();

				// TODO Add confirmation message to login redirect
				Session::clear('AutoLoginHash');

				// Clear locked out status
				$security = $member->MemberSecurity();
				$security->LockedOutUntil = null;
				$security->FailedLoginCount = null;
				$security->write();

				if (!empty($_REQUEST['BackURL'])
					// absolute redirection URLs may cause spoofing
					&& Director::is_site_url($_REQUEST['BackURL'])
				) {
					$url = Director::absoluteURL($_REQUEST['BackURL']);

					return $this->controller->redirect($url);
				} else {
					// Redirect to default location - the login form saying "You are logged in as..."
					$redirectURL = HTTP::setGetVar( // @todo WAT
						'BackURL',
						Director::absoluteBaseURL(), $this->controller->Link('login')
					);

					return $this->controller->redirect($redirectURL);
				}
			} else {
				$message = _t(
					'Member.INVALIDNEWPASSWORD',
					"We couldn't accept that password: {password}",
					array('password' => nl2br("\n" . Convert::raw2xml($isValid->starredList())))
				);
				$type = 'bad';

				return $this->changePasswordError($message, $type);

			}

		} else {
			// Passwords not the same
			$message = _t('Member.ERRORNEWPASSWORD', 'You have entered your new password differently, try again');
			$type = 'bad';

			return $this->changePasswordError($message, $type);
		}
	}

}

