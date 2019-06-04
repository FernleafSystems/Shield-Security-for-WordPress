<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	/**
	 * @return string[]
	 */
	protected function getAdditionalDisplayStrings() {
		return [
			'um_current_user_settings'          => __( 'Current User Sessions', 'wp-simple-firewall' ),
			'um_username'                       => __( 'Username', 'wp-simple-firewall' ),
			'um_logged_in_at'                   => __( 'Logged In At', 'wp-simple-firewall' ),
			'um_last_activity_at'               => __( 'Last Activity At', 'wp-simple-firewall' ),
			'um_last_activity_uri'              => __( 'Last Activity URI', 'wp-simple-firewall' ),
			'um_login_ip'                       => __( 'Login IP', 'wp-simple-firewall' ),
			'um_need_to_enable_user_management' => __( 'You need to enable the User Management feature to view and manage user sessions.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( $sSectionSlug ) {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_user_accounts_management' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'User Management offers real user sessions, finer control over user session time-out, and ensures users have logged-in in a correct manner.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_passwords' :
				$sTitle = __( 'Password Policies', 'wp-simple-firewall' );
				$sTitleShort = __( 'Password Policies', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Have full control over passwords used by users on the site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Requirements', 'wp-simple-firewall' ), sprintf( 'WordPress v%s+', '4.4.0' ) ),
				];
				break;

			case 'section_admin_login_notification' :
				$sTitle = __( 'Admin Login Notification', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'So you can be made aware of when a WordPress administrator has logged into your site when you are not expecting it.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Notifications', 'wp-simple-firewall' );
				break;

			case 'section_multifactor_authentication' :
				$sTitle = __( 'Multi-Factor User Authentication', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Verifies the identity of users who log in to your site - i.e. they are who they say they are.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ).' '.__( 'However, if your host blocks email sending you may lock yourself out.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Multi-Factor Authentication', 'wp-simple-firewall' );
				break;

			case 'section_user_session_management' :
				$sTitle = __( 'User Session Management', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Allows you to better control user sessions on your site and expire idle sessions and prevent account sharing.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Session Options', 'wp-simple-firewall' );
				break;

			case 'section_suspend' :
				$sTitleShort = __( 'User Suspension', 'wp-simple-firewall' );
				$sTitle = __( 'Automatic And Manual User Suspension', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Automatically suspends accounts to prevent login by certain users.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				return parent::getSectionStrings( $sSectionSlug );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( $sOptKey ) {
		$oOptsVo = $this->getMod()->getOptionsVo();
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $sOptKey ) {

			case 'enable_user_management' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'enable_admin_login_email_notification' :
				$sName = __( 'Admin Login Notification Email', 'wp-simple-firewall' );
				$sSummary = __( 'Send An Notification Email When Administrator Logs In', 'wp-simple-firewall' );
				$sDescription = __( 'If you would like to be notified every time an administrator user logs into this WordPress site, enter a notification email address.', 'wp-simple-firewall' )
								.'<br />'.__( 'No email address - No Notification.', 'wp-simple-firewall' );
				break;

			case 'enable_user_login_email_notification' :
				$sName = __( 'User Login Notification Email', 'wp-simple-firewall' );
				$sSummary = __( 'Send Email Notification To Each User Upon Successful Login', 'wp-simple-firewall' );
				$sDescription = __( 'A notification is sent to each user when a successful login occurs for their account.', 'wp-simple-firewall' );
				break;

			case 'session_timeout_interval' :
				$sName = __( 'Session Timeout', 'wp-simple-firewall' );
				$sSummary = __( 'Specify How Many Days After Login To Automatically Force Re-Login', 'wp-simple-firewall' );
				$sDescription = __( 'WordPress default is 2 days, or 14 days if you check the "Remember Me" box.', 'wp-simple-firewall' )
								.'<br />'.__( 'Think of this as an absolute maximum possible session length.', 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'This cannot be less than %s.', 'wp-simple-firewall' ), '<strong>1</strong>' )
								.' '.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), '<strong>'.$oOptsVo->getOptDefault( 'session_timeout_interval' ).'</strong>' );
				break;

			case 'session_idle_timeout_interval' :
				$sName = __( 'Idle Timeout', 'wp-simple-firewall' );
				$sSummary = __( 'Specify How Many Hours After Inactivity To Automatically Logout User', 'wp-simple-firewall' );
				$sDescription = __( 'If the user is inactive for the number of hours specified, they will be forcefully logged out next time they return.', 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'Set to %s to turn off this option.', 'wp-simple-firewall' ), '"<strong>0</strong>"' );
				break;

			case 'session_lock_location' :
				$sName = __( 'Lock To Location', 'wp-simple-firewall' );
				$sSummary = __( 'Locks A User Session To IP address', 'wp-simple-firewall' );
				$sDescription = __( 'When selected, a session is restricted to the same IP address as when the user logged in.', 'wp-simple-firewall' )
								.' '.__( "If a logged-in user's IP address changes, the session will be invalidated and they'll be forced to re-login to WordPress.", 'wp-simple-firewall' );
				break;

			case 'session_username_concurrent_limit' :
				$sName = __( 'Max Simultaneous Sessions', 'wp-simple-firewall' );
				$sSummary = __( 'Limit Simultaneous Sessions For The Same Username', 'wp-simple-firewall' );
				$sDescription = __( 'The number provided here is the maximum number of simultaneous, distinct, sessions allowed for any given username.', 'wp-simple-firewall' )
								.'<br />'.__( "Zero (0) will allow unlimited simultaneous sessions.", 'wp-simple-firewall' );
				break;

			case 'enable_password_policies' :
				$sName = __( 'Enable Password Policies', 'wp-simple-firewall' );
				$sSummary = __( 'Enable The Password Policies Detailed Below', 'wp-simple-firewall' );
				$sDescription = __( 'Turn on/off all password policy settings.', 'wp-simple-firewall' );
				break;

			case 'pass_prevent_pwned' :
				$sName = __( 'Prevent Pwned Passwords', 'wp-simple-firewall' );
				$sSummary = __( 'Prevent Use Of "Pwned" Passwords', 'wp-simple-firewall' );
				$sDescription = __( 'Prevents users from using any passwords found on the public available list of "pwned" passwords.', 'wp-simple-firewall' );
				break;

			case 'pass_min_length' :
				$sName = __( 'Minimum Length', 'wp-simple-firewall' );
				$sSummary = __( 'Minimum Password Length', 'wp-simple-firewall' );
				$sDescription = __( 'All passwords that a user sets must be at least this many characters in length.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' );
				break;

			case 'pass_min_strength' :
				$sName = __( 'Minimum Strength', 'wp-simple-firewall' );
				$sSummary = __( 'Minimum Password Strength', 'wp-simple-firewall' );
				$sDescription = __( 'All passwords that a user sets must meet this minimum strength.', 'wp-simple-firewall' );
				break;

			case 'pass_force_existing' :
				$sName = __( 'Apply To Existing Users', 'wp-simple-firewall' );
				$sSummary = __( 'Apply Password Policies To Existing Users and Their Passwords', 'wp-simple-firewall' );
				$sDescription = __( "Forces existing users to update their passwords if they don't meet requirements, after they next login.", 'wp-simple-firewall' )
								.'<br/>'.__( 'Note: You may want to warn users prior to enabling this option.', 'wp-simple-firewall' );
				break;

			case 'pass_expire' :
				$sName = __( 'Password Expiration', 'wp-simple-firewall' );
				$sSummary = __( 'Passwords Expire After This Many Days', 'wp-simple-firewall' );
				$sDescription = __( 'Users will be forced to reset their passwords after the number of days specified.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' );
				break;

			case 'manual_suspend' :
				$sName = __( 'Allow Manual User Suspension', 'wp-simple-firewall' );
				$sSummary = __( 'Manually Suspend User Accounts To Prevent Login', 'wp-simple-firewall' );
				$sDescription = __( 'Users may be suspended by administrators to prevent future login.', 'wp-simple-firewall' );
				break;

			case 'auto_password' :
				$sName = __( 'Auto-Suspend Expired Passwords', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Suspend Users With Expired Passwords', 'wp-simple-firewall' );
				$sDescription = __( 'Automatically suspends login by users and requires password reset to unsuspend.', 'wp-simple-firewall' )
								.'<br/>'.sprintf(
									'<strong>%s</strong> - %s',
									__( 'Important', 'wp-simple-firewall' ),
									__( 'Requires password expiration policy to be set.', 'wp-simple-firewall' )
								);
				break;

			case 'auto_idle_days' :
				$sName = __( 'Auto-Suspend Idle Users', 'wp-simple-firewall' );
				$sSummary = __( 'Automatically Suspend Idle User Accounts', 'wp-simple-firewall' );
				$sDescription = __( 'Automatically suspends login for idle accounts and requires password reset to unsuspend.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Specify the number of days since last login to consider a user as idle.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Set to Zero(0) to disable.', 'wp-simple-firewall' );
				break;

			case 'auto_idle_roles' :
				$sName = __( 'Auto-Suspend Idle User Roles', 'wp-simple-firewall' );
				$sSummary = __( 'Apply Automatic Suspension To Accounts With These Roles', 'wp-simple-firewall' );
				$sDescription = __( 'Automatic suspension for idle accounts applies only to the roles you specify.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Take a new line for each user role.', 'wp-simple-firewall' ) )
								.'<br/>'.sprintf( '%s: %s', __( 'Available Roles', 'wp-simple-firewall' ), implode( ', ', Services::WpUsers()
																																  ->getAvailableUserRoles() ) )
								.'<br/>'.sprintf( '%s: %s', __( 'Default', 'wp-simple-firewall' ), implode( ', ', $oOptsVo->getOptDefault( 'auto_idle_roles' ) ) );
				break;

			default:
				return parent::getOptionStrings( $sOptKey );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'pass_expired'        => [
				__( 'Forcing user to update expired password.', 'wp-simple-firewall' ),
			],
			'pass_policy_change'  => [
				__( 'Forcing user to update password that fails to meet policies.', 'wp-simple-firewall' ),
			],
			'pass_policy_block'   => [
				__( 'Blocked attempted password update that failed policy requirements.', 'wp-simple-firewall' ),
			],
			'session_notfound'    => [
				__( 'Valid user session could not be found.', 'wp-simple-firewall' ),
				__( 'Logging out.', 'wp-simple-firewall' )
			],
			'session_expired'     => [
				__( 'User session has expired.', 'wp-simple-firewall' ),
				__( 'Logging out.', 'wp-simple-firewall' )
			],
			'session_idle'        => [
				__( 'User session has expired due to inactivity.', 'wp-simple-firewall' ),
				__( 'Logging out.', 'wp-simple-firewall' )
			],
			'session_iplock'      => [
				__( 'Access to an established user session from a different IP address.', 'wp-simple-firewall' ),
				__( 'Logging out.', 'wp-simple-firewall' )
			],
			'session_browserlock' => [
				__( 'Browser signature has changed for this user session.', 'wp-simple-firewall' ),
				__( 'Logging out.', 'wp-simple-firewall' )
			],
			'session_unverified'  => [
				__( 'Unable to verify the current User Session. Forcefully logging out session.', 'wp-simple-firewall' ),
				__( 'Logging out.', 'wp-simple-firewall' )
			],
		];
	}
}