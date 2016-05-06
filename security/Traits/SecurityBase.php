<?php

namespace SilverStripe\Security;

/**
 * Class SecurityBase
 * This is a trait, because it might be useful somewhere else as well.
 * For now, probably only Security uses it.
 *
 * @package SilverStripe\Security
 */
trait SecurityBase
{

	public function changePasswordError($message, $type, $target = 'changepassword')
	{
		$this->clearMessage();
		$this->sessionMessage(
			$message,
			$type
		);

		// redirect back to the form, instead of using redirectBack() which could send the user elsewhere.
		return $this->controller->redirect($this->controller->Link($target));
	}
}
