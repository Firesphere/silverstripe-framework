<?php

namespace SilverStripe\Security;

use CheckboxField;
use Config;
use Controller;
use Convert;
use Director;
use Email;
use FieldList;
use FormAction;
use FormField;
use HiddenField;
use LiteralField;
use Member;
use PasswordField;
use RequiredFields;
use Requirements;
use Session;
use SiteTree;
use SS_HTTPResponse;
use TextField;

/**
 * Log-in form for the "member" authentication method.
 *
 * Available extension points:
 * - "authenticationFailed": Called when login was not successful.
 *    Arguments: $data containing the form submission
 * - "forgotPassword": Called before forgot password logic kicks in,
 *    allowing extensions to "veto" execution by returning FALSE.
 *    Arguments: $member containing the detected Member record
 *
 * @package framework
 * @subpackage security
 */
class MemberLoginForm extends LoginForm
{

	/**
	 * Constant defining the method for forgot password
	 */
	const ACTION_FORGOT_PASSWORD_METHOD = 'forgotPassword';
	/**
	 * Constant defining the method for logging in
	 */
	const LOGIN_METHOD = 'doLogin';

	/**
	 * This field is used in the "You are logged in as %s" message
	 *
	 * @config
	 *
	 * @var string
	 */
	public $loggedInAsField = 'FirstName';

	/**
	 * Default authenticator, can be overridden in config.
	 *
	 * @config
	 *
	 * @var string
	 */
	protected static $authenticator_class = 'SilverStripe\Security\MemberAuthenticator';

	/**
	 * Controller using this class.
	 *
	 * @var SiteTree
	 */
	protected $controller;

	/**
	 * Since the logout and dologin actions may be conditionally removed, it's necessary to ensure these
	 * remain valid actions regardless of the member login state.
	 *
	 * @var array
	 * @config
	 */
	private static $allowed_actions = array(
		'dologin',
		'logout'
	);

	/**
	 * Constructor
	 *
	 * @todo write description
	 * @todo clean this up. It's messy.
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
	 *                                     {@link FormAction} objects
	 * @param bool $checkCurrentUser If set to TRUE, it will be checked if a
	 *                               the user is currently logged in, and if
	 *                               so, only a logout button will be rendered
	 */
	public function __construct($controller, $name, $fields = null, $actions = null,
								$checkCurrentUser = true)
	{

		$this->controller = $controller;

		$customCSS = project() . '/css/member_login.css';
		if (Director::fileExists($customCSS)) {
			Requirements::css($customCSS);
		}

		$backURL = $this->controller->getRequest()->getVar('BackURL');

		if (null === $this->controller->getRequest()->getVar('BackURL')) {
			$backURL = Session::get('BackURL');
		}

		if ($checkCurrentUser && Member::currentUser() && Member::logged_in_session_exists()) {
			/** @var FieldList $fields */
			$fields = FieldList::create(
				HiddenField::create('AuthenticationMethod', null, self::$authenticator_class, $this)
			);
			/** @var FieldList $actions */
			$actions = FieldList::create(
				FormAction::create('logout', _t('Member.BUTTONLOGINOTHER', 'Log in as someone else'))
			);
		} else {
			if (!$fields) {
				$label = singleton('Member')->fieldLabel(Member::config()->get('unique_identifier_field'));
				/** @var FieldList $fields */
				$fields = FieldList::create(
					HiddenField::create('AuthenticationMethod', null, self::$authenticator_class, $this),
					// Regardless of what the unique identifer field is (usually 'Email'), it will be held in the
					// 'Email' value, below:
					/** @var TextField $loginIdentifierField */
					$loginIdentifierField = TextField::create('Email', $label, null, null, $this),
					PasswordField::create('Password', _t('Member.PASSWORD', 'Password'))
				);
				if (Config::inst()->get('Security', 'remember_username')) {
					$loginIdentifierField->setValue(Session::get('SessionForms.MemberLoginForm.Email'));
				} else {
					// Some browsers won't respect this attribute unless it's added to the form
					$this->setAttribute('autocomplete', 'off');
					$loginIdentifierField->setAttribute('autocomplete', 'off');
				}
				if (Config::inst()->get('Security', 'autologin_enabled') === true) {
					$fields->push(
					/** @var CheckboxField $rememberCheckbox */
						$rememberCheckbox = CheckboxField::create(
							'Remember',
							_t('Member.KEEPMESIGNEDIN', 'Keep me signed in')
						)
					);
					$rememberCheckbox->setAttribute(
						'title',
						sprintf(
							_t('Member.REMEMBERME', 'Remember me next time? (for %d days on this device)'),
							Config::inst()->get('RememberLoginHash', 'token_expiry_days')
						)
					);
				}
			}
			if (!$actions) {
				$actions = FieldList::create(
					FormAction::create('dologin', _t('Member.BUTTONLOGIN', 'Log in')),
					LiteralField::create(
						'forgotPassword',
						'<p id="ForgotPassword"><a href="' . Config::inst()->get('Security', 'forgot_password_url') . '">'
						. _t('Member.BUTTONLOSTPASSWORD', "I've lost my password") . '</a></p>'
					)
				);
			}
		}

		if (null !== $backURL) {
			$fields->push(HiddenField::create('BackURL', 'BackURL', $backURL));
		}

		// Reduce attack surface by enforcing POST requests
		$this->setFormMethod('POST', true);

		parent::__construct($controller, $name, $fields, $actions);

		$this->setValidator(RequiredFields::create('Email', 'Password'));

		// Focus on the email input when the page is loaded
		$js = <<<JS
			(function() {
				var el = document.getElementById("MemberLoginForm_LoginForm_Email");
				if(el && el.focus && (typeof jQuery == 'undefined' || jQuery(el).is(':visible'))) el.focus();
			})();
JS;
		Requirements::customScript($js, 'MemberLoginFormFieldFocus');
	}

	/**
	 * Get message from session
	 */
	protected function getMessageFromSession()
	{

		$forceMessage = Session::get('MemberLoginForm.force_message');
		if (($member = Member::currentUser()) && !$forceMessage) {
			$this->message = _t(
				'Member.LOGGEDINAS',
				"You're logged in as {name}.",
				array('name' => $member->{$this->loggedInAsField})
			);
		}

		// Reset forced message
		if ($forceMessage) {
			Session::set('MemberLoginForm.force_message', false);
		}

		return parent::getMessageFromSession();
	}


	/**
	 * Login form handler method
	 *
	 * This method is called when the user clicks on "Log in"
	 *
	 * @param array $data Submitted data
	 */
	public function dologin($data)
	{
		$backURL = $this->controller->getRequest()->getVar('BackURL');
		if ($this->performLogin($data)) {
			$this->logInUserAndRedirect($data);
		} else {
			if (array_key_exists('Email', $data)) {
				Session::set('SessionForms.MemberLoginForm.Email', $data['Email']);
				Session::set('SessionForms.MemberLoginForm.Remember', array_key_exists('Remember', $data));
			}

			if (null === $backURL) {
				Session::set('BackURL', $backURL);
			}

			// Show the right tab on failed login
			$loginLink = Director::absoluteURL($this->controller->Link('login'));
			if ($backURL) {
				$loginLink .= '?BackURL=' . urlencode($backURL);
			}
			$this->controller->redirect($loginLink . '#' . $this->FormName() . '_tab');
		}
	}

	/**
	 * Login in the user and figure out where to redirect the browser.
	 *
	 * The $data has this format
	 * array(
	 *   'AuthenticationMethod' => 'MemberAuthenticator',
	 *   'Email' => 'sam@silverstripe.com',
	 *   'Password' => '1nitialPassword',
	 *   'BackURL' => 'test/link',
	 *   [Optional: 'Remember' => 1 ]
	 * )
	 *
	 * @param array $data
	 *
	 * @return SS_HTTPResponse
	 */
	protected function logInUserAndRedirect($data)
	{
		Session::clear('SessionForms.MemberLoginForm.Email');
		Session::clear('SessionForms.MemberLoginForm.Remember');
		$backURL = $this->controller->getRequest()->getVar('BackURL');

		if (Member::currentUser()->MemberSecurity()->isPasswordExpired()) {
			if (null !== $backURL) {
				Session::set('BackURL', $backURL);
			}
			$cp = ChangePasswordForm::create($this->controller, 'ChangePasswordForm');
			$cp->sessionMessage(
				_t('Member.PASSWORDEXPIRED', 'Your password has expired. Please choose a new one.'),
				'good'
			);

			return $this->controller->redirect('Security/changepassword');
		}

		// Absolute redirection URLs may cause spoofing
		if (null !== $backURL) {
			$url = $backURL;
			if (Director::is_site_url($url)) {
				$url = Director::absoluteURL($url);
			} else {
				// Spoofing attack, redirect to homepage instead of spoofing url
				$url = Director::absoluteBaseURL();
			}

			return $this->controller->redirect($url);
		}

		// If a default login dest has been set, redirect to that.
		if ($url = Config::inst()->get('Security', 'default_login_dest')) {
			$url = Controller::join_links(Director::absoluteBaseURL(), $url);

			return $this->controller->redirect($url);
		}

		// Redirect the user to the page where they came from
		/** @var Member $member */
		$member = Member::currentUser();
		if (null !== $member) {
			$firstName = Convert::raw2xml($member->FirstName);
			if (!empty($data['Remember'])) {
				Session::set('SessionForms.MemberLoginForm.Remember', '1');
				$member->logIn(true);
			} else {
				$member->logIn();
			}

			Session::set('Security.Message.message',
				_t('Member.WELCOMEBACK', 'Welcome Back, {firstname}', array('firstname' => $firstName))
			);
			Session::set('Security.Message.type', 'good');
		}
		$this->controller->redirectBack();

		return null;
	}


	/**
	 * Log out form handler method
	 *
	 * This method is called when the user clicks on "logout" on the form
	 * created when the parameter <i>$checkCurrentUser</i> of the
	 * {@link __construct constructor} was set to TRUE and the user was
	 * currently logged in.
	 */
	public function logout()
	{
		$s = Security::create();
		$s->logout();
	}


	/**
	 * Try to authenticate the user
	 *
	 * @param array $data Submitted data
	 *
	 * @return Member Returns the member object on successful authentication
	 *                or NULL on failure.
	 */
	public function performLogin($data)
	{
		/** @var Member $member */
		$member = call_user_func_array(array(self::$authenticator_class, 'authenticate'), array($data, $this));
		if (null !== $member) {
			$member->logIn(array_key_exists('Remember', $data));

			return $member;
		} else {
			$this->extend('authenticationFailed', $data);

			return null;
		}
	}


	/**
	 * Forgot password form handler method.
	 * Called when the user clicks on "I've lost my password".
	 * Extensions can use the 'forgotPassword' method to veto executing
	 * the logic, by returning FALSE. In this case, the user will be redirected back
	 * to the form without further action. It is recommended to set a message
	 * in the form detailing why the action was denied.
	 *
	 * @param array $data Submitted data
	 *
	 * @return SS_HTTPResponse|void
	 */
	public function forgotPassword($data)
	{
		$uniqueField = Config::inst()->get('Security', 'unique_identifier_field');
		$lostPasswordURL = Config::inst()->get('Security', 'forgot_password_url');
		$sentPasswordURL = Config::inst()->get('Security', 'sent_password_url');

		// Ensure password is given
		if (false === array_key_exists('Email', $data)) {
			$this->sessionMessage(
				_t('Member.ENTEREMAIL', 'Please enter an email address to get a password reset link.'),
				'bad'
			);

			$this->controller->redirect($lostPasswordURL);

			return null;
		}

		// Find existing member
		/** @var Member $member */
		$member = Member::get()->filter($uniqueField, $data['Email'])->first();

		// Allow vetoing forgot password requests
		$results = $this->extend('forgotPassword', $member);
		if ($results && is_array($results) && in_array(false, $results, true)) {
			return $this->controller->redirect($lostPasswordURL);
		}

		if ($member) {
			$token = $member->generateAutologinTokenAndStoreHash();

			/** @var Email $e */
			$e = Email::create();
			$e->setSubject(_t('Member.SUBJECTPASSWORDRESET', "Your password reset link", 'Email subject'));
			$e->setTemplate('ForgotPasswordEmail');
			$e->populateTemplate($member);
			$e->populateTemplate(array(
				'PasswordResetLink' => Security::getPasswordResetLink($member, $token)
			));
			$e->setTo($member->Email);
			$e->send();

			$this->controller->redirect($sentPasswordURL . '/' . urlencode($data['Email']));
		} elseif ($data['Email']) {
			// Avoid information disclosure by displaying the same status,
			// regardless wether the email address actually exists
			$this->controller->redirect($sentPasswordURL . rawurlencode($data['Email']));
		} else {
			$this->sessionMessage(
				_t('Member.ENTEREMAIL', 'Please enter an email address to get a password reset link.'),
				'bad'
			);

			$this->controller->redirect($lostPasswordURL);
		}

		return null;
	}

	/**
	 * @return array
	 */
	public static function getAllowedActions()
	{
		return self::$allowed_actions;
	}

	/**
	 * @param array $allowed_actions
	 */
	public static function setAllowedActions($allowed_actions)
	{
		self::$allowed_actions = $allowed_actions;
	}

}

