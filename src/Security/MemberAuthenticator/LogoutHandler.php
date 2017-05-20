<?php

namespace SilverStripe\Security\MemberAuthenticator;

use SilverStripe\Control\Cookie;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Control\Session;
use SilverStripe\Security\Member;
use SilverStripe\Security\RememberLoginHash;
use SilverStripe\Security\Security;

/**
 * Class LogoutHandler handles logging out Members from their session and/or cookie.
 * The logout process destroys all traces of the member on the server (not the actual computer user
 * at the other end of the line, don't worry)
 *
 * @package SilverStripe\Security\MemberAuthenticator
 */
class LogoutHandler extends RequestHandler
{
    /**
     * @var array
     */
    private static $url_handlers = [
        '' => 'logout'
    ];

    /**
     * @var array
     */
    private static $allowed_actions = [
        'logout'
    ];


    /**
     * Log out form handler method
     *
     * This method is called when the user clicks on "logout" on the form
     * created when the parameter <i>$checkCurrentUser</i> of the
     * {@link __construct constructor} was set to TRUE and the user was
     * currently logged in.
     *
     * @return bool|Member
     */
    public function logout()
    {
        $member = Security::getCurrentUser();

        return $this->doLogOut($member);
    }

    /**
     *
     * @param Member $member
     * @return bool|Member Return a member if something goes wrong
     */
    public function doLogOut($member)
    {
        if ($member instanceof Member) {
            Session::clear('loggedInAs');
            if (Member::config()->get('login_marker_cookie')) {
                Cookie::set(Member::config()->get('login_marker_cookie'), null, 0);
            }

            Session::destroy();

            // Clears any potential previous hashes for this member
            RememberLoginHash::clear($member, Cookie::get('alc_device'));

            Cookie::set('alc_enc', null); // // Clear the Remember Me cookie
            Cookie::force_expiry('alc_enc');
            Cookie::set('alc_device', null);
            Cookie::force_expiry('alc_device');

            // Switch back to live in order to avoid infinite loops when
            // redirecting to the login screen (if this login screen is versioned)
            Session::clear('readingMode');

            // Remove the member from Security, for Security reasons
            Security::setCurrentUser(null);
        }

        return true;
    }
}
