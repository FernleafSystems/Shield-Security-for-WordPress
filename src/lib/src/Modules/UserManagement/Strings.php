<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Services\Services;

class Strings extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Strings {

	public function getPassStrengthName( int $strength ) :string {
		return [
				   __( 'Very Weak', 'wp-simple-firewall' ),
				   __( 'Weak', 'wp-simple-firewall' ),
				   __( 'Medium', 'wp-simple-firewall' ),
				   __( 'Strong', 'wp-simple-firewall' ),
				   __( 'Very Strong', 'wp-simple-firewall' ),
			   ][ \max( 0, \min( 4, $strength ) ) ];
	}

	public function getEventStrings() :array {
		return [];
	}

	public function getSectionStrings( string $section ) :array {
		switch ( $section ) {

			case 'section_enable_plugin_feature_user_accounts_management' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ),
					$this->mod()->getMainFeatureName() );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'User Management offers real user sessions, finer control over user session time-out, and ensures users have logged-in in a correct manner.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_passwords' :
				$title = __( 'Password Policies', 'wp-simple-firewall' );
				$titleShort = __( 'Password Policies', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Have full control over passwords used by users on the site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Requirements', 'wp-simple-firewall' ), sprintf( 'WordPress v%s+', '4.4.0' ) ),
				];
				break;

			case 'section_admin_login_notification' :
				$title = __( 'Admin Login Notification', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'So you can be made aware of when a WordPress administrator has logged into your site when you are not expecting it.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Notifications', 'wp-simple-firewall' );
				break;

			case 'section_multifactor_authentication' :
				$title = __( 'Multi-Factor User Authentication', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ).' '.__( 'However, if your host blocks email sending you may lock yourself out.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Multi-Factor Authentication', 'wp-simple-firewall' );
				break;

			case 'section_user_session_management' :
				$title = __( 'User Session Management', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Allows you to better control user sessions on your site and expire idle sessions and prevent account sharing.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Session Options', 'wp-simple-firewall' );
				break;

			case 'section_suspend' :
				$titleShort = __( 'User Suspension', 'wp-simple-firewall' );
				$title = __( 'Automatic And Manual User Suspension', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatically suspends accounts to prevent login by certain users.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $title,
			'title_short' => $titleShort,
			'summary'     => $summary,
		];
	}

	public function getOptionStrings( string $key ) :array {
		$opts = $this->opts();
		$name = $this->mod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_user_management' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $name );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $name );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $name ) ];
				break;

			case 'enable_admin_login_email_notification' :
				$name = __( 'Admin Login Notification Email', 'wp-simple-firewall' );
				$summary = __( 'Send An Notification Email When Administrator Logs In', 'wp-simple-firewall' );
				$desc = [
					__( 'If you would like to be notified every time an administrator user logs into this WordPress site, enter a notification email address.', 'wp-simple-firewall' ),
					__( 'No email address - No Notification.', 'wp-simple-firewall' ),
				];
				$desc[] = self::con()->isPremiumActive() ?
					__( 'Multiple email addresses may be supplied, separated by a comma.', 'wp-simple-firewall' ) :
					__( 'Please upgrade your plan if you need to notify multiple email addresses.', 'wp-simple-firewall' );
				break;

			case 'enable_user_login_email_notification' :
				$name = __( 'User Login Notification Email', 'wp-simple-firewall' );
				$summary = __( 'Send Email Notification To Each User Upon Successful Login', 'wp-simple-firewall' );
				$desc = [ __( 'A notification is sent to each user when a successful login occurs for their account.', 'wp-simple-firewall' ) ];
				break;

			case 'session_timeout_interval' :
				$name = __( 'Session Timeout', 'wp-simple-firewall' );
				$summary = __( 'Specify How Many Days After Login To Automatically Force Re-Login', 'wp-simple-firewall' );
				$desc = [
					__( 'WordPress default is 2 days, or 14 days if you check the "Remember Me" box.', 'wp-simple-firewall' ),
					__( 'Think of this as an absolute maximum possible session length.', 'wp-simple-firewall' ),
					sprintf( __( 'This cannot be less than %s.', 'wp-simple-firewall' ), '<strong>1</strong>' ),
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), '<strong>'.$opts->getOptDefault( 'session_timeout_interval' ).'</strong>' )
				];
				break;

			case 'session_idle_timeout_interval' :
				$name = __( 'Idle Timeout', 'wp-simple-firewall' );
				$summary = __( 'Specify How Many Hours After Inactivity To Automatically Logout User', 'wp-simple-firewall' );
				$desc = [
					__( 'If the user is inactive for the number of hours specified, they will be forcefully logged out next time they return.', 'wp-simple-firewall' ),
					sprintf( __( 'Set to %s to turn off this option.', 'wp-simple-firewall' ), '"<strong>0</strong>"' )
				];
				break;

			case 'session_lock' :
				$name = __( 'User Session Lock', 'wp-simple-firewall' );
				$summary = __( 'Locks A User Session To Prevent Theft', 'wp-simple-firewall' );
				$desc = [
					__( 'Protects against user compromise by preventing user session theft/hijacking.', 'wp-simple-firewall' ),
				];
				break;

			case 'reg_email_validate' :
				$name = __( 'Validate Email Addresses', 'wp-simple-firewall' );
				$summary = __( 'Validate Email Addresses When User Attempts To Register', 'wp-simple-firewall' );
				$desc = [
					__( 'Validate Email Addresses When User Attempts To Register.', 'wp-simple-firewall' ),
					__( 'To validate an email your site sends a request to the ShieldNET API and may cause a small delay during the user registration request.', 'wp-simple-firewall' ),
				];
				break;

			case 'email_checks' :
				$name = __( 'Email Validation Checks', 'wp-simple-firewall' );
				$summary = __( 'The Email Address Properties That Will Be Tested', 'wp-simple-firewall' );
				$desc = [ __( 'Select the properties that should be tested during email address validation.', 'wp-simple-firewall' ) ];
				break;

			case 'enable_password_policies' :
				$name = __( 'Enable Password Policies', 'wp-simple-firewall' );
				$summary = __( 'Enable The Password Policies Detailed Below', 'wp-simple-firewall' );
				$desc = [ __( 'Turn on/off all password policy settings.', 'wp-simple-firewall' ) ];
				break;

			case 'pass_prevent_pwned' :
				$name = __( 'Prevent Pwned Passwords', 'wp-simple-firewall' );
				$summary = __( 'Prevent Use Of "Pwned" Passwords', 'wp-simple-firewall' );
				$desc = [ __( 'Prevents users from using any passwords found on the public available list of "pwned" passwords.', 'wp-simple-firewall' ) ];
				break;

			case 'pass_min_strength' :
				$name = __( 'Minimum Strength', 'wp-simple-firewall' );
				$summary = __( 'Minimum Password Strength', 'wp-simple-firewall' );
				$desc = [ __( 'All passwords that a user sets must meet this minimum strength.', 'wp-simple-firewall' ) ];
				break;

			case 'pass_force_existing' :
				$name = __( 'Apply To Existing Users', 'wp-simple-firewall' );
				$summary = __( 'Apply Password Policies To Existing Users and Their Passwords', 'wp-simple-firewall' );
				$desc = [
					__( "Forces existing users to update their passwords if they don't meet requirements, after they next login.", 'wp-simple-firewall' ),
					__( 'Note: You may want to warn users prior to enabling this option.', 'wp-simple-firewall' )
				];
				break;

			case 'pass_expire' :
				$name = __( 'Password Expiration', 'wp-simple-firewall' );
				$summary = __( 'Passwords Expire After This Many Days', 'wp-simple-firewall' );
				$desc = [
					__( 'Users will be forced to reset their passwords after the number of days specified.', 'wp-simple-firewall' ),
					__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' )
				];
				break;

			case 'manual_suspend' :
				$name = __( 'Allow Manual User Suspension', 'wp-simple-firewall' );
				$summary = __( 'Manually Suspend User Accounts To Prevent Login', 'wp-simple-firewall' );
				$desc = [ __( 'Users may be suspended by administrators to prevent future login.', 'wp-simple-firewall' ) ];
				break;

			case 'auto_password' :
				$name = __( 'Auto-Suspend Expired Passwords', 'wp-simple-firewall' );
				$summary = __( 'Automatically Suspend Users With Expired Passwords', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatically suspends login by users and requires password reset to unsuspend.', 'wp-simple-firewall' ),
					sprintf(
						'<strong>%s</strong> - %s',
						__( 'Important', 'wp-simple-firewall' ),
						__( 'Requires password expiration policy to be set.', 'wp-simple-firewall' )
					)
				];
				break;

			case 'auto_idle_days' :
				$name = __( 'Auto-Suspend Idle Users', 'wp-simple-firewall' );
				$summary = __( 'Automatically Suspend Idle User Accounts', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatically suspends login for idle accounts and requires password reset to unsuspend.', 'wp-simple-firewall' ),
					__( 'Specify the number of days since last login to consider a user as idle.', 'wp-simple-firewall' ),
					__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' )
				];
				break;

			case 'auto_idle_roles' :
				$name = __( 'Auto-Suspend Idle User Roles', 'wp-simple-firewall' );
				$summary = __( 'Apply Automatic Suspension To Accounts With These Roles', 'wp-simple-firewall' );
				$desc = [
					__( 'Automatic suspension for idle accounts applies only to the roles you specify.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Take a new line for each user role.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Available Roles', 'wp-simple-firewall' ),
						\implode( ', ', Services::WpUsers()->getAvailableUserRoles() ) ),
					sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), \implode( ', ', $opts->getOptDefault( 'auto_idle_roles' ) ) )
				];
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}
}