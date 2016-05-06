<?php

namespace SilverStripe\Security;
use Form;
use Injector;

/**
 * Abstract base class for a login form
 *
 * This class is used as a base class for the different log-in forms like
 * {@link MemberLoginForm} or {@link OpenIDLoginForm}.
 *
 * @author Markus Lanthaler <markus@silverstripe.com>
 * @package framework
 * @subpackage security
 */
<<<<<<< 352552fae227a9a1a266302d01d9b69082bcceef:security/LoginForm.php
abstract class LoginForm extends Form {
=======
abstract class LoginForm extends Form
{
	public function __construct($controller, $name, $fields, $actions)
	{
		parent::__construct($controller, $name, $fields, $actions);

		$this->disableSecurityToken();
	}
>>>>>>> Security to namespacing and refactor.:security/Forms/LoginForm.php

	/**
	 * Authenticator class to use with this login form
	 *
	 * Set this variable to the authenticator class to use with this login
	 * form.
	 *
	 * @var string
	 */

	protected static $authenticator_class;

	/**
	 * Get the authenticator instance
	 *
	 * @return Authenticator Returns the authenticator instance for this login form.
	 */
	public function getAuthenticator()
	{
		if (!class_exists(self::$authenticator_class) || !is_subclass_of(self::$authenticator_class, 'Authenticator')) {
			user_error("The form uses an invalid authenticator class! '{self::$authenticator_class}'"
				. " is not a subclass of 'Authenticator'", E_USER_ERROR);

			return;
		}

		return Injector::inst()->get(self::$authenticator_class);
	}

	/**
	 * Get the authenticator name.
	 *
	 * @return string The friendly name for use in templates, etc.
	 */
	public function getAuthenticatorName()
	{
		/** @var Authenticator $authClass */
		$authClass = self::$authenticator_class;

		return $authClass::get_name();
	}

}

