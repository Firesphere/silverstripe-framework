<?php

namespace SilverStripe\Security;

use ArrayData;
use ArrayList;
use ClassInfo;
use Config;
use Controller;
use Convert;
use DataObject;
use DB;
use Director;
use Exception;
use FieldList;
use Form;
use FormAction;
use SilverStripe\Model\FieldType\DBHTMLText as HTMLText;
use Member;
use Page;
use Page_Controller;
use Psr\Log\InvalidArgumentException;
use Session;
use SS_HTTPRequest;
use SS_HTTPResponse;
use TemplateGlobalProvider;
use TextField;

/**
 * Implements a basic extendable security model.
 *
 * @author Simon 'Firesphere' Erkelens
 * @package framework
 * @subpackage security
 */
class Security extends Controller implements TemplateGlobalProvider
{

	use SecurityBase;
	use SecurityBuild;
	use SecurityDeprecation;

	private static $allowed_actions = array(
		'index',
		'login',
		'logout',
		'basicauthlogin',
		'lostpassword',
		'passwordsent',
		'changepassword',
		'passwordchanged', // @todo create a password-changed page.
		'ping',
		'LoginForm',
		'ChangePasswordForm',
		'LostPasswordForm',
	);

	/**
	 * Default user name. Only used in dev-mode by {@link setDefaultAdmin()}
	 *
	 * @config
	 *
	 * @var string
	 */
	protected static $default_username;

	/**
	 * Default password. Only used in dev-mode by {@link setDefaultAdmin()}
	 *
	 * @config
	 *
	 * @var string
	 */
	protected static $default_password;

	/**
	 * If set to TRUE to prevent sharing of the session across several sites
	 * in the domain.
	 *
	 * @config
	 * @var bool
	 */
	protected static $strict_path_checking;

	/**
	 * If set to true, disallowed actions is ignored
	 *
	 * @config Security.ignore_disallowed_actions
	 * @var bool
	 */
	protected static $ignore_disallowed_actions;

	/**
	 * The password encryption algorithm to use by default.
	 * This is an arbitrary code registered through {@link PasswordEncryptor}.
	 *
	 * @config
	 * @var string
	 */
	private static $password_encryption_algorithm;

	/**
	 * @config
	 * @var string Set the default login dest
	 * This is the URL that users will be redirected to after they log in,
	 * if they haven't logged in en route to access a secured page.
	 * By default, this is set to the homepage.
	 */
	private static $default_login_dest;


	/**
	 * Showing "Remember me"-checkbox
	 * on loginform, and saving encrypted credentials to a cookie.
	 *
	 * @config
	 * @var bool
	 */
	private static $autologin_enabled;

	/**
	 * Determine if login username may be remembered between login sessions
	 * If set to false this will disable autocomplete and prevent username persisting in the session
	 *
	 * @config
	 * @var bool
	 */
	private static $remember_username;

	/**
	 * Location of word list to use for generating passwords
	 *
	 * @config
	 * @var string
	 */
	private static $word_list;

	/**
	 * @config
	 * @var string
	 */
	private static $template;

	/**
	 * Template thats used to render the pages.
	 *
	 * @var string
	 * @config
	 */
	private static $template_main;

	/**
	 * Default message set used in permission failures.
	 *
	 * @config
	 * @var array|string
	 */
	private static $default_message_set;

	/**
	 * Random secure token, can be used as a crypto key internally.
	 * Generate one through 'sake dev/generatesecuretoken'.
	 *
	 * @config
	 * @var String
	 */
	private static $token;

	/**
	 * Base url for reaching security. Default: Security
	 *
	 * @config
	 *
	 * @var
	 */
	private static $base_url;

	/**
	 * The default login URL
	 *
	 * @config
	 *
	 * @var string
	 */
	private static $login_url;

	/**
	 * The default logout URL
	 *
	 * @config
	 *
	 * @var string
	 */
	private static $logout_url;

	/**
	 * The default url for when the member has forgotten his/her password
	 *
	 * @config
	 *
	 * @var string
	 */
	private static $forgot_password_url;

	/**
	 * The default url when the password was lost and a reset-link has been sent.
	 *
	 * @config
	 *
	 * @var string
	 */
	private static $sent_password_url;

	/**
	 * The default url for changing the password
	 *
	 * @config
	 *
	 * @var string
	 */
	private static $change_password_url;

	/**
	 * Enable or disable recording of login attempts
	 * through the {@link LoginRecord} object.
	 *
	 * @config
	 * @var boolean $login_recording
	 */
	private static $login_recording;

	/**
	 * @var boolean If set to TRUE or FALSE, {@link database_is_ready()}
	 * will always return FALSE. Used for unit testing.
	 */
	protected static $force_database_is_ready;

	/**
	 * When the database has once been verified as ready, it will not do the
	 * checks again.
	 *
	 * @var bool
	 */
	protected static $database_is_ready = false;

	/**
	 * Register that we've had a permission failure trying to view the given page
	 *
	 * This will redirect to a login page.
	 * If you don't provide a messageSet, a default will be used.
	 *
	 * @param Controller $controller The controller that you were on to cause the permission
	 *                               failure.
	 * @param string|array $messageSet The message to show to the user. This
	 *                                 can be a string, or a map of different
	 *                                 messages for different contexts.
	 *                                 If you pass an array, you can use the
	 *                                 following keys:
	 *                                   - default: The default message
	 *                                   - alreadyLoggedIn: The message to
	 *                                                      show if the user
	 *                                                      is already logged
	 *                                                      in and lacks the
	 *                                                      permission to
	 *                                                      access the item.
	 *
	 * The alreadyLoggedIn value can contain a '%s' placeholder that will be replaced with a link
	 * to log in.
	 *
	 * @return SS_HTTPResponse
	 */
	public static function permissionFailure($controller = null, $messageSet = null)
	{
		self::set_ignore_disallowed_actions(true);

		if (null === $controller) {
			$controller = Controller::curr();
		}

		if (Director::is_ajax()) {
			return self::ajaxResponse($controller);
		}

		$messageSet = self::getPermissionFailureMessage($messageSet);

		/** @var Member $member */
		$member = Member::currentUser();

		// Work out the right message to show
		if ($member && $member->exists()) {
			$response = $controller ? $controller->getResponse() : new SS_HTTPResponse();
			$response->setStatusCode(403);

			//If 'alreadyLoggedIn' is not specified in the array, then use the default
			//which should have been specified in the lines above
			if (array_key_exists('alreadyLoggedIn', $messageSet)) {
				$message = $messageSet['alreadyLoggedIn'];
			} else {
				$message = $messageSet['default'];
			}

			// Somewhat hackish way to render a login form with an error message.
			/** @var self $me */
			$me = self::create();
			/** @var LoginForm $form */
			$form = $me->LoginForm();
			$form->sessionMessage($message, 'warning');
			Session::set('MemberLoginForm.force_message', 1);
			$formText = $me->login();

			$response->setBody($formText);

			$controller->extend('permissionDenied', $member);

			return $response;
		} else {
			$message = $messageSet['default'];
		}

		Session::set('Security.Message.message', $message);
		Session::set('Security.Message.type', 'warning');

		$backUri = $controller->getRequest()->getURL(false);

		Session::set('BackURL', $backUri);

		// TODO AccessLogEntry needs an extension to handle permission denied errors
		// Audit logging hook
		$controller->extend('onAfterPermissionFailure', $member);

		return $controller->redirect(
			Config::inst()->get('Security', 'login_url')
			. '?BackURL=' . Convert::raw2url($backUri)
		);
	}

	public function init()
	{
		parent::init();

		// Prevent clickjacking, see https://developer.mozilla.org/en-US/docs/HTTP/X-Frame-Options
		$this->getResponse()->addHeader('X-Frame-Options', 'SAMEORIGIN');
	}

	public function index()
	{
		/** @noinspection PhpVoidFunctionResultUsedInspection */
		return $this->httpError(404); // no-op
	}

	/**
	 * Get the selected authenticator for this request
	 *
	 * @return string Class name of Authenticator
	 * @throws SecurityException
	 */
	protected function getAuthenticator()
	{
		$authenticator = $this->getRequest()->requestVar('AuthenticationMethod');
		if ($authenticator) {
			$authenticators = array_flip(Authenticator::get_authenticators()); // @todo fix this weird bug
			if (array_key_exists($authenticator, $authenticators)) {
				return $authenticator;
			} else {
				throw new SecurityException('Authenticator not found', 255);
			}
		} else {
			return Authenticator::get_default_authenticator();
		}

	}

	/**
	 * Get the login form to process according to the submitted data
	 *
	 * @return Form
	 * @throws Exception
	 */
	public function LoginForm()
	{
		/** @var Authenticator $authenticator */
		$authenticator = $this->getAuthenticator();
		if ($authenticator) {
			return $authenticator::get_login_form($this);
		}
		throw new InvalidArgumentException('Passed invalid authentication method');
	}

	/**
	 * Get the login forms for all available authentication methods
	 *
	 * @return ArrayList Returns an array of available login forms (array of Form
	 *               objects).
	 *
	 * @todo Check how to activate/deactivate authentication methods
	 */
	public function getLoginForms()
	{
		/** @var ArrayList $forms */
		$forms = ArrayList::create();

		$authenticators = Authenticator::get_authenticators();
		/** @var Authenticator $authenticator */
		foreach ($authenticators as $authenticator) {
			$forms->push($authenticator::get_login_form($this));
		}

		return $forms;
	}

	/**
	 * Get a link to a security action
	 *
	 * @param string $action Name of the action
	 *
	 * @return string Returns the link to the given action
	 */
	public function Link($action = null)
	{
		return Controller::join_links(Director::baseURL(), Config::inst()->get('Security', 'base_url'), $action);
	}

	/**
	 * This action is available as a keep alive, so user
	 * sessions don't timeout. A common use is in the admin.
	 */
	public function ping()
	{
		return 1;
	}

	/**
	 * Log the currently logged in user out
	 *
	 * @param bool $redirect Redirect the user back to where they came.
	 *                       - If it's false, the code calling logout() is
	 *                         responsible for sending the user where-ever
	 *                         they should go.
	 *
	 * @return HTMLText
	 */
	public function logout($redirect = false)
	{
		/** @var Member $member */
		$member = Member::currentUser();
		if ($member !== null) {
			$member->logOut();
		}

		return $this->loggedOutMessage($redirect);
	}

	/**
	 * @param bool|SS_HTTPRequest $request
	 *
	 * @return HTMLText
	 */
	public function loggedOutMessage($request = false)
	{
		// @todo Shamelessly copied from Controller. Should be extracted to a separate finder-function.
		$backURL = '/';
		if ($request !== false) {
			if ($request->requestVar('BackURL')) {
				$backURL = $request->requestVar('BackURL');
			} else if ($request->isAjax() && $request->getHeader('X-Backurl')) {
				$backURL = $request->getHeader('X-Backurl');
			} else if ($request->getHeader('Referer')) {
				$backURL = $request->getHeader('Referer');
			}
		}

		$controller = $this->getResponseController(_t('Security.LOGGEDOUT', 'Logged out'));
		$customisedController = $controller->customise(
			array(
				'Content'     => _t('Security.LOGGEDOUTCONTENT', 'You are now logged out.<br />Click below to go back to where you came from, or continue browsing the website normally.<br /><a href="{backURL}">Back</a>',
					array('backURL' => $backURL)),
				'Message'     => _t('Security.LOGGEDOUTMESSAGE', 'You are logged out.'),
				'MessageType' => 'good',
			));

		return $customisedController->renderWith(
			$this->getTemplatesFor('loggedout')
		);
	}

	/**
	 * Perform pre-login checking and prepare a response if available prior to login
	 *
	 * @return SS_HTTPResponse Substitute response object if the login process should be curcumvented.
	 * Returns null if should proceed as normal.
	 */
	protected function preLogin()
	{
		// Event handler for pre-login, with an option to let it break you out of the login form
		$eventResults = $this->extend('onBeforeSecurityLogin');

		// If there was a redirection, return
		if ($this->redirectedTo()) {
			return $this->getResponse();
		}
		// If there was an SS_HTTPResponse object returned, then return that
		if ($eventResults) {
			foreach ($eventResults as $result) {
				if ($result instanceof SS_HTTPResponse) {
					return $result;
				}
			}
		}

		// If arriving on the login page already logged in, with no security error, and a ReturnURL then redirect
		// back. The login message check is neccesary to prevent infinite loops where BackURL links to
		// an action that triggers Security::permissionFailure.
		// This step is necessary in cases such as automatic redirection where a user is authenticated
		// upon landing on an SSL secured site and is automatically logged in, or some other case
		// where the user has permissions to continue but is not given the option.
		if (null !== $this->getLoginMessage()
			&& $this->getRequest()->requestVar('BackURL')
			&& ($member = Member::currentUser())
			&& $member->exists()
		) {
			return $this->redirectBack();
		}

		return false;
	}

	/**
	 * Prepare the controller for handling the response to this request
	 *
	 * @param string $title Title to use
	 *
	 * @return Controller
	 */
	protected function getResponseController($title)
	{
		if (!class_exists('SiteTree')) {
			return $this;
		}

		// Use sitetree pages to render the security page
		/** @var Page $tmpPage */
		$tmpPage = Page::create();
		$tmpPage->Title = $title;
		$tmpPage->URLSegment = 'Security';
		// Disable ID-based caching  of the log-in page by making it a random number
		$tmpPage->ID = -1 * mt_rand(1, 10000000);

		/** @var Page_Controller $controller */
		$controller = Page_Controller::create($tmpPage);
		$controller->setDataModel($this->model);
		$controller->init();

		return $controller;
	}

	/**
	 * Determine the list of templates to use for rendering the given action
	 *
	 * @param string $action
	 *
	 * @return array Template list
	 */
	public function getTemplatesFor($action)
	{
		return array("Security_{$action}", 'Security', $this->stat('template_main'), 'BlankPage');
	}

	/**
	 * Combine the given forms into a formset with a tabbed interface
	 *
	 * @param ArrayList $forms List of LoginForm instances
	 *
	 * @return string
	 */
	protected function generateLoginFormSet($forms)
	{
		/** @var ArrayData $viewData */
		$viewData = ArrayData::create(array(
			'Forms' => $forms,
		));

		return $viewData->renderWith(
			$this->getIncludeTemplate('MultiAuthenticatorLogin')
		);
	}

	/**
	 * Get the HTML Content for the $Content area during login
	 *
	 * @return string Message in HTML format
	 *
	 */
	protected function getLoginMessage($message = null)
	{
		if($message === null) {
			$message = Session::get('Security.Message.message');
		}
		$messageType = null;
		/** @noinspection IsEmptyFunctionUsageInspection */
		if (empty($message)) {
			return null;
		}

		$messageType = Session::get('Security.Message.type');
		if ($messageType === 'bad') {
			return "<p class=\"message $messageType\">$message</p>";
		} else {
			return "<p>$message</p>";
		}
	}

	/**
	 * Show the "login" page
	 *
	 * For multiple authenticators, Security_MultiAuthenticatorLogin is used.
	 * See getTemplatesFor and getIncludeTemplate for how to override template logic
	 *
	 * @return string Returns the "login" page as HTML code.
	 */
	public function login()
	{
		// Check pre-login process
		if ($response = $this->preLogin()) {
			return $response;
		}

		// Get response handler
		$controller = $this->getResponseController(_t('Security.LOGIN', 'Log in'));

		// if the controller calls Director::redirect(), this will break early
		if (($response = $controller->getResponse()) && $response->isFinished()) {
			return $response;
		}

		$forms = $this->getLoginForms();
		if (!count($forms)) {
			user_error('No login-forms found, please use Authenticator::register_authenticator() to add one',
				E_USER_ERROR);
		}

		// Handle any form messages from validation, etc.
		$messageType = '';
		/** @noinspection PhpMethodParametersCountMismatchInspection */
		$message = $this->getLoginMessage($messageType);

		// We've displayed the message in the form output, so reset it for the next run.
		Session::clear('Security.Message');

		// only display tabs when more than one authenticator is provided
		// to save bandwidth and reduce the amount of custom styling needed
		if (count($forms) > 1) {
			$content = $this->generateLoginFormSet($forms);
		} else {
			$content = $forms->first()->forTemplate();
		}

		// Finally, customise the controller to add any form messages and the form.
		$customisedController = $controller->customise(array(
			'Content'     => $message,
			'Message'     => $message,
			'MessageType' => $messageType,
			'Form'        => $content,
		));

		// Return the customised controller
		return $customisedController->renderWith(
			$this->getTemplatesFor('login')
		);
	}

	public function basicauthlogin()
	{
		$member = BasicAuth::requireLogin('SilverStripe login', 'ADMIN');
		$member->logIn();
	}

	/**
	 * Show the "lost password" page
	 *
	 * @todo create extension hook.
	 *
	 * @return string Returns the "lost password" page as HTML code.
	 */
	public function lostpassword()
	{
		$controller = $this->getResponseController(_t('Security.LOSTPASSWORDHEADER', 'Lost Password'));

		// if the controller calls Director::redirect(), this will break early
		if (($response = $controller->getResponse()) && $response->isFinished()) {
			return $response;
		}

		$content = '<p>' .
			_t(
				'Security.NOTERESETPASSWORD',
				'Enter your e-mail address and we will send you a link with which you can reset your password'
			) .
			'</p>';
		$customisedController = $controller->customise(array(
			'Content' => $content,
			'Form'    => $this->LostPasswordForm(),
		));

		return $customisedController->renderWith($this->getTemplatesFor('lostpassword'));
	}


	/**
	 * Factory method for the lost password form
	 *
	 * @return Form Returns the lost password form
	 */
	public function LostPasswordForm()
	{
		/** @var FieldList $userNameField */
		$userNameField = FieldList::create(
			TextField::create('Email', _t('Member.EMAIL', 'Email'))
		);
		/** @var FieldList $actionField */
		$actionField = FieldList::create(
			FormAction::create(
				MemberLoginForm::ACTION_FORGOT_PASSWORD_METHOD,
				_t('Security.BUTTONSEND', 'Send me the password reset link')
			)
		);

		return MemberLoginForm::create(
			$this,
			__FUNCTION__,
			$userNameField,
			$actionField,
			false
		);
	}


	/**
	 * Show the "password sent" page, after a user has requested
	 * to reset their password.
	 *
	 * @todo add callback functionality to update texts
	 *
	 * @param SS_HTTPRequest $request The SS_HTTPRequest for this action.
	 *
	 * @return string Returns the "password sent" page as HTML code.
	 */
	public function passwordsent($request)
	{
		$controller = $this->getResponseController(_t('Security.LOSTPASSWORDHEADER', 'Lost Password'));

		// if the controller calls Director::redirect(), this will break early
		if (($response = $controller->getResponse()) && $response->isFinished()) {
			return $response;
		}

		$email = Convert::raw2xml(rawurldecode($request->param('ID')) . '.' . $request->getExtension());
		$content = '<p>'
			. _t('Security.PASSWORDSENTTEXT',
				"Thank you! A reset link has been sent to '{email}', provided an account exists for this email"
				. ' address.',
				array('email' => $email))
			. '</p>';
		$customisedController = $controller->customise(array(
			'Title'   => _t('Security.PASSWORDSENTHEADER', "Password reset link sent to '{email}'",
				array('email' => $email)),
			'Content' => $content,
			'Email'   => $email
		));

		return $customisedController->renderWith($this->getTemplatesFor('passwordsent'));
	}


	/**
	 * Create a link to the password reset form.
	 *
	 * GET parameters used:
	 * - m: member ID
	 * - t: plaintext token
	 *
	 * @param Member $member Member object associated with this link.
	 * @param string $autologinToken The auto login token.
	 *
	 * @return string
	 */
	public static function getPasswordResetLink($member, $autologinToken)
	{
		$autologinToken = urldecode($autologinToken);
		/** @var Security $selfController */
		$selfController = Controller::curr();

		return $selfController->Link('changepassword') . "?m={$member->ID}&t=$autologinToken";
	}

	/**
	 * Show the "change password" page.
	 * This page can either be called directly by logged-in users
	 * (in which case they need to provide their old password),
	 * or through a link emailed through {@link lostpassword()}.
	 * In this case no old password is required, authentication is ensured
	 * through the Member.AutoLoginHash property.
	 *
	 * @see ChangePasswordForm
	 *
	 * @todo cleanup. It's a bit messy/unreadable.
	 *
	 * @return string Returns the "change password" page as HTML code.
	 */
	public function changepassword()
	{
		$controller = $this->getResponseController(_t('Security.CHANGEPASSWORDHEADER', 'Change your password'));
		$request = Controller::curr()->getRequest();
		// if the controller calls Director::redirect(), this will break early
		if (($response = $controller->getResponse()) && $response->isFinished()) {
			return $response;
		}

		/** @var string $t Token */
		$token = $request->getVar('t');
		/** @var int $m MemberID */
		$memberID = (int)$request->getVar('m');

		// Extract the member from the URL.
		$member = null;
		if (null !== $memberID) {
			/** @var Member $member */
			$member = Member::get()->filter('ID', $memberID)->first();
		}


		// Check whether we are merely changin password, or resetting.
		if (null !== $member && null !== $token && $member->validateAutoLoginToken($token)) {

			// if there is a current member, they should be logged out
			if ($curMember = Member::currentUser()) {
				$curMember->logOut();
			}

			// Store the hash for the change password form. Will be unset after reload within the ChangePasswordForm.
			Session::set('AutoLoginHash', $member->encryptWithUserSettings($token));

			// On first valid password reset request redirect to the same URL without hash to avoid referrer leakage.
			return $this->redirect($this->Link('changepassword'));
		} elseif (Session::get('AutoLoginHash')) {
			// Subsequent request after the "first load with hash" (see previous if clause).
			$customisedController = $controller->customise(array(
				'Content' =>
					'<p>' .
					_t('Security.ENTERNEWPASSWORD', 'Please enter a new password.') .
					'</p>',
				'Form'    => $this->ChangePasswordForm(),
			));
		} elseif (Member::currentUser()) {
			// Logged in user requested a password change form.
			$customisedController = $controller->customise(array(
				'Content' => '<p>'
					. _t('Security.CHANGEPASSWORDBELOW', 'You can change your password below.') . '</p>',
				'Form'    => $this->ChangePasswordForm()));

		} else {
			// Show friendly message if it seems like the user arrived here via password reset feature.
			if (null !== $token || null !== $memberID) {
				$customisedController = $controller->customise(
					array('Content' =>
							  _t(
								  'Security.NOTERESETLINKINVALID',
								  '<p>The password reset link is invalid or expired.</p>'
								  . '<p>You can request a new one <a href="{link1}">here</a> or change your password after'
								  . ' you <a href="{link2}">logged in</a>.</p>',
								  array(
									  'link1' => $this->Link('lostpassword'),
									  'link2' => $this->Link('login')
								  )
							  )
					)
				);
			} else {
				self::permissionFailure(
					$this,
					_t('Security.ERRORPASSWORDPERMISSION', 'You must be logged in in order to change your password!')
				);

				return null;
			}
		}

		return $customisedController->renderWith($this->getTemplatesFor('changepassword'));
	}

	/**
	 * Factory method for the lost password form
	 *
	 * @return Form Returns the lost password form
	 */
	public function ChangePasswordForm()
	{
		return ChangePasswordForm::create($this, 'ChangePasswordForm');
	}

	/**
	 * Display a nice message to the user that his/her password has been reset.
	 * And give the option to go back to wherever they were trying to go to before resetting
	 * the password.
	 *
	 * @return HTMLText
	 */
	public function passwordreset()
	{

	}

	/**
	 * Gets the template for an include used for security.
	 * For use in any subclass.
	 *
	 * @param string $name
	 *
	 * @return array|string Returns the template(s) for rendering
	 */
	public function getIncludeTemplate($name)
	{
		return array('Security_' . $name);
	}


	/**
	 * Flush the default admin credentials
	 */
	public static function clear_default_admin()
	{
		Config::inst()->update('Security', 'default_username', null);
		Config::inst()->update('Security', 'default_password', null);
	}

	/**
	 * Checks if the passed credentials are matching the default-admin.
	 * Compares cleartext-password set through Security::setDefaultAdmin().
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return bool
	 */
	public static function check_default_admin($username, $password)
	{
		$config = Config::inst()->forClass('Security');
		if (self::has_default_admin()
			&& $config->get('default_username') === $username
			&& $config->get('default_password') === $password
		) {
			return true;
		}

		return false;
	}

	/**
	 * Check that the default admin account has been set.
	 */
	public static function has_default_admin()
	{
		$defaultUsername = Config::inst()->get('Security', 'default_username');
		$defaultPassword = Config::inst()->get('Security', 'default_password');

		/** @noinspection IsEmptyFunctionUsageInspection */
		return (!empty($defaultUsername) && !empty($defaultPassword));
	}

	/**
	 * Get default admin username
	 *
	 * @return string
	 */
	public static function default_admin_username()
	{
		return Config::inst()->get('Security', 'default_username');
	}

	/**
	 * Get default admin password
	 *
	 * @return string
	 */
	public static function default_admin_password()
	{
		return Config::inst()->get('Security', 'default_password');
	}

	/**
	 * Return a response to an ajax request from permissionfailure.
	 *
	 * @param Controller $controller
	 *
	 * @return SS_HTTPResponse
	 */
	public static function ajaxResponse($controller)
	{
		/** @var SS_HTTPResponse $response */
		$response = $controller ? $controller->getResponse() : SS_HTTPResponse::create();
		$response->setStatusCode(403);
		if (null === Member::currentUser()) {
			$response->setBody(_t('ContentController.NOTLOGGEDIN', 'Not logged in'));
			$response->setStatusDescription(_t('ContentController.NOTLOGGEDIN', 'Not logged in'));
			// Tell the CMS to allow re-aunthentication
			if (true === CMSSecurity::enabled()) {
				$response->addHeader('X-Reauthenticate', '1');
			}
		}

		return $response;
	}

	/**
	 * Get the default message setup for permission failures.
	 *
	 * @param string|array $messageSet
	 *
	 * @return array
	 */
	protected static function getPermissionFailureMessage($messageSet)
	{
		// Prepare the messageSet provided
		if (!$messageSet) {
			if ($configMessageSet = Config::inst()->get('Security', 'default_message_set')) {
				$messageSet = $configMessageSet;
			} else {
				$messageSet = [
					'default'         => _t(
						'Security.NOTEPAGESECURED',
						'That page is secured. Enter your credentials below and we will send '
						. 'you right along.'
					),
					'alreadyLoggedIn' => _t(
						'Security.ALREADYLOGGEDIN',
						"You don't have access to this page. If you have another account that "
						. 'can access that page, you can log in again below.',

						'%s will be replaced with a link to log in.'
					)
				];
			}
		}

		if (!is_array($messageSet)) {
			$messageSet = array('default' => $messageSet);
		}

		return $messageSet;
	}

	/**
	 * Checks the database is in a state to perform security checks.
	 * See {@link DatabaseAdmin->init()} for more information.
	 *
	 * @return bool
	 */
	public static function database_is_ready()
	{
		// Used for unit tests
		if (self::$force_database_is_ready !== null || self::$database_is_ready !== false) {
			return self::$force_database_is_ready;
		}

		$requiredTables = ClassInfo::dataClassesFor('Member');
		$requiredTables[] = 'Group';
		$requiredTables[] = 'Permission';

		foreach ($requiredTables as $table) {
			// Skip test classes, as not all test classes are scaffolded at once
			if (is_subclass_of($table, 'TestOnly')) {
				continue;
			}

			// if any of the tables aren't created in the database
			if (!ClassInfo::hasTable($table)) {
				return false;
			}

			// HACK: DataExtensions aren't applied until a class is instantiated for
			// @remark doesn't sound like a hack to me. It makes perfect sense.
			// the first time, so create an instance here.
			singleton($table);

			// if any of the tables don't have all fields mapped as table columns
			$dbFields = DB::field_list($table);
			if (!$dbFields) {
				return false;
			}

			$objFields = DataObject::database_fields($table);
			$missingFields = array_diff_key($objFields, $dbFields);

			if ($missingFields) {
				return false;
			}
		}
		self::$database_is_ready = true;

		return true;
	}

	/**
	 * Set to true to ignore access to disallowed actions, rather than returning permission failure
	 * Note that this is just a flag that other code needs to check with Security::ignore_disallowed_actions()
	 *
	 * @param $flag True or false
	 */
	public static function set_ignore_disallowed_actions($flag)
	{
		Config::inst()->update('Security', 'ignore_disallowed_actions', $flag);
	}

	/**
	 * Returns config setting for disallowed actions ignoring.
	 *
	 * @return bool
	 */
	public static function ignore_disallowed_actions()
	{
		return Config::inst()->get('Security', 'ignore_disallowed_actions');
	}

	/**
	 * Get the URL of the log-in page.
	 *
	 * To update the login url use the "Security.login_url" config setting.
	 *
	 * @return string
	 */
	public static function login_url()
	{
		return Config::inst()->get('Security', 'login_url');
	}

	/**
	 * Get the URL of the logout page.
	 *
	 * To update the logout url use the "Security.logout_url" config setting.
	 *
	 * @return string
	 */
	public static function logout_url()
	{
		return Config::inst()->get('Security', 'logout_url');
	}

	/**
	 * Defines global accessible templates variables.
	 *
	 * @return array
	 */
	public static function get_template_global_variables()
	{
		return array(
			'LoginURL'  => 'login_url',
			'LogoutURL' => 'logout_url',
		);
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

	/**
	 * @return string
	 */
	public static function getDefaultUsername()
	{
		return Config::inst()->get('Security', 'default_username');
	}

}
