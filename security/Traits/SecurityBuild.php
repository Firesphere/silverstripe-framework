<?php
/**
 * Created by IntelliJ IDEA.
 * User: simon
 * Date: 11/04/16
 * Time: 21:25
 */

namespace SilverStripe\Security;

use Group;
use Member;
use Permission;

/**
 * Class SecurityBuild
 *
 * Methods in this trait are used on built only.
 * Therefore they're extracted to a separate trait, to keep the Security class clean.
 * I understand it's not really a trait.
 *
 * @package SilverStripe\Security
 */
trait SecurityBuild
{

	/**
	 * Return an existing member with administrator privileges, or create one of necessary.
	 *
	 * Will create a default 'Administrators' group if no group is found
	 * with an ADMIN permission. Will create a new 'Admin' member with administrative permissions
	 * if no existing Member with these permissions is found.
	 *
	 * Important: Any newly created administrator accounts will NOT have valid
	 * login credentials (Email/Password properties), which means they can't be used for login
	 * purposes outside of any default credentials set through ConfigureFromEnv.
	 *
	 * @return Member
	 */
	public static function findAnAdministrator()
	{

		$member = null;

		// find a group with ADMIN permission
		/** @var Group $adminGroup */
		$adminGroup = Permission::get_groups_by_permission('ADMIN')->first();

		// Get the first member.
		if (null !== $adminGroup) {
			$member = $adminGroup->Members()->first();
		}

		// Or create a new admingroup
		if (null === $adminGroup) {
			singleton('Group')->requireDefaultRecords();
			$adminGroup = Permission::get_groups_by_permission('ADMIN')->first();
		}

		// If no member is found, get the first from the default records
		if (null === $member) {
			singleton('Member')->requireDefaultRecords();
			$member = Permission::get_members_by_permission('ADMIN')->first();
		}

		// If still not found, grab from the Member.
		if (null === $member) {
			$member = Member::default_admin();
		}

		// Still no admin? Create a new, blank admin without username/password (unable to login)
		if (null === $member) {
			// Failover to a blank admin
			/** @var Member $member */
			$member = Member::create();
			$member->FirstName = _t('Member.DefaultAdminFirstname', 'Default Admin');
			$member->write();
			// Add member to group instead of adding group to member
			// This bypasses the privilege escallation code in MemberGroupSet
			$adminGroup
				->DirectMembers()
				->add($member);
		}

		return $member;
	}
}
