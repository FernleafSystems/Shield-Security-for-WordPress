<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() :array {
		return [
			'key_success' => [
				__( 'Successful authentication using Security Admin PIN.', 'wp-simple-firewall' ),
			],
			'key_fail'    => [
				__( 'Failed authentication using Security Admin PIN.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {
		$sPlugName = $this->getCon()->getHumanName();

		switch ( $section ) {

			case 'section_enable_plugin_feature_admin_access_restriction' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to this plugin preventing unauthorized changes to your security settings.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) ) ),
					sprintf( __( 'You need to also enter a new Security PIN to enable this feature.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_admin_access_restriction_settings' :
				$sTitle = __( 'Security Admin Restriction Settings', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to this plugin preventing unauthorized changes to your security settings.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				$sTitleShort = __( 'Security Admin Settings', 'wp-simple-firewall' );
				break;

			case 'section_admin_access_restriction_areas' :
				$sTitle = __( 'Security Admin Restriction Zones', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to key WordPress areas for all users not authenticated with the Security Admin Access system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				$sTitleShort = __( 'Access Restriction Zones', 'wp-simple-firewall' );
				break;

			case 'section_whitelabel' :
				$sTitle = __( 'White Label', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s',
						__( 'Purpose', 'wp-simple-firewall' ),
						sprintf( __( 'Rename and re-brand the %s plugin for your client site installations.', 'wp-simple-firewall' ),
							$sPlugName )
					),
					sprintf( '%s - %s',
						__( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'The Security Admin system must be active for these settings to apply.', 'wp-simple-firewall' ),
							$sPlugName )
					)
				];
				$sTitleShort = __( 'White Label', 'wp-simple-firewall' );
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		$sPlugName = $this->getCon()->getHumanName();

		switch ( $key ) {

			case 'enable_admin_access_restriction' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) );
				$sSummary = __( 'Enforce Security Admin Access Restriction', 'wp-simple-firewall' );
				$sDescription = __( "Enable this with great care and consideration. Ensure that you set an Security PIN that you'll remember.", 'wp-simple-firewall' );
				break;

			case 'admin_access_key' :
				$sName = __( 'Security Admin PIN', 'wp-simple-firewall' );
				$sSummary = __( 'Provide/Update Security Admin PIN', 'wp-simple-firewall' );
				$sDescription = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'If you forget this, you could potentially lock yourself out from using this plugin.', 'wp-simple-firewall' ) ),
					'<strong>'.( $oOpts->hasSecurityPIN() ? __( 'Security PIN Currently Set', 'wp-simple-firewall' ) : __( 'Security PIN NOT Currently Set', 'wp-simple-firewall' ) ).'</strong>',
					$oOpts->hasSecurityPIN() ? sprintf( __( 'To delete the current security PIN, type exactly "%s" and save.', 'wp-simple-firewall' ), '<strong>DELETE</strong>' ) : ''
				];
				break;

			case 'sec_admin_users' :
				$sName = __( 'Security Admins', 'wp-simple-firewall' );
				$sSummary = __( 'Persistent Security Admins', 'wp-simple-firewall' );
				$sDescription = __( "Users provided will be security admins automatically, without needing the security PIN.", 'wp-simple-firewall' )
								.'<br/>'.__( 'Enter admin username, email or ID.', 'wp-simple-firewall' ).' '.__( '1 entry per-line.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Verified users will be converted to usernames.', 'wp-simple-firewall' ) );
				break;

			case 'admin_access_timeout' :
				$sName = __( 'Security Admin Timeout', 'wp-simple-firewall' );
				$sSummary = __( 'Specify An Automatic Timeout Interval For Security Admin Access', 'wp-simple-firewall' );
				$sDescription = __( 'This will automatically expire your Security Admin Session.', 'wp-simple-firewall' )
								.'<br />'
								.sprintf(
									'%s: %s',
									__( 'Default', 'wp-simple-firewall' ),
									sprintf( '%s minutes', $oOpts->getOptDefault( 'admin_access_timeout' ) )
								);
				break;

			case 'allow_email_override' :
				$sName = __( 'Allow Email Override', 'wp-simple-firewall' );
				$sSummary = __( 'Allow Email Override Of Admin Access Restrictions', 'wp-simple-firewall' );
				$sDescription = __( 'Allow the use of verification emails to override and switch off the Security Admin restrictions.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( __( "The email address specified in %s's General settings will be used.", 'wp-simple-firewall' ), $sPlugName );
				break;

			case 'admin_access_restrict_posts' :
				$sName = __( 'Pages', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To Key WordPress Posts And Pages Actions', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to page/post creation, editing and deletion.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ), __( 'Edit', 'wp-simple-firewall' ) ) );
				break;

			case 'admin_access_restrict_plugins' :
				$sName = __( 'Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To Key WordPress Plugin Actions', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to plugin installation, update, activation and deletion.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ), __( 'Activate', 'wp-simple-firewall' ) ) );
				break;

			case 'admin_access_restrict_options' :
				$sName = __( 'WordPress Options', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To Certain WordPress Admin Options', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict the ability of WordPress administrators from changing key WordPress settings.', 'wp-simple-firewall' ) );
				break;

			case 'admin_access_restrict_admin_users' :
				$sName = __( 'Admin Users', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To Create/Delete/Modify Other Admin Users', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict the ability of WordPress administrators from creating, modifying or promoting other administrators.', 'wp-simple-firewall' ) );
				break;

			case 'admin_access_restrict_themes' :
				$sName = __( 'Themes', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To WordPress Theme Actions', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to theme installation, update, activation and deletion.', 'wp-simple-firewall' ) )
								.'<br />'.
								sprintf( '%s: %s',
									__( 'Note', 'wp-simple-firewall' ),
									sprintf(
										__( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ),
										sprintf(
											__( '%s and %s', 'wp-simple-firewall' ),
											__( 'Activate', 'wp-simple-firewall' ),
											__( 'Edit Theme Options', 'wp-simple-firewall' )
										)
									)
								);
				break;

			case 'whitelabel_enable' :
				$sName = sprintf( '%s: %s', __( 'Enable', 'wp-simple-firewall' ), __( 'White Label', 'wp-simple-firewall' ) );
				$sSummary = __( 'Activate Your White Label Settings', 'wp-simple-firewall' );
				$sDescription = __( 'Turn on/off the application of your White Label settings.', 'wp-simple-firewall' );
				break;
			case 'wl_hide_updates' :
				$sName = __( 'Hide Updates', 'wp-simple-firewall' );
				$sSummary = __( 'Hide Plugin Updates From Non-Security Admins', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'Hide available %s updates from non-security administrators.', 'wp-simple-firewall' ), $sPlugName );
				break;
			case 'wl_replace_badge_url' :
				$sName = __( 'Plugin Name', 'wp-simple-firewall' );
				$sSummary = __( 'The Name Of The Plugin', 'wp-simple-firewall' );
				$sDescription = __( 'The name of the plugin that will be displayed to your site users.', 'wp-simple-firewall' );
				break;
			case 'wl_pluginnamemain' :
				$sName = __( 'Replace Plugin Badge', 'wp-simple-firewall' );
				$sSummary = __( 'Replace Plugin Badge URL and Images', 'wp-simple-firewall' );
				$sDescription = __( 'When using the plugin badge, replace the URL and link with your Whitelabel settings.', 'wp-simple-firewall' );
				break;
			case 'wl_namemenu' :
				$sName = __( 'Menu Title', 'wp-simple-firewall' );
				$sSummary = __( 'The Main Menu Title Of The Plugin', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'The Main Menu Title Of The Plugin. If left empty, the "%s" will be used.', 'wp-simple-firewall' ), __( 'Plugin Name', 'wp-simple-firewall' ) );
				break;
			case 'wl_companyname' :
				$sName = __( 'Company Name', 'wp-simple-firewall' );
				$sSummary = __( 'The Name Of Your Company', 'wp-simple-firewall' );
				$sDescription = __( 'Provide the name of your company.', 'wp-simple-firewall' );
				break;
			case 'wl_description' :
				$sName = __( 'Description', 'wp-simple-firewall' );
				$sSummary = __( 'The Description Of The Plugin', 'wp-simple-firewall' );
				$sDescription = __( 'The description of the plugin displayed on the plugins page.', 'wp-simple-firewall' );
				break;
			case 'wl_homeurl' :
				$sName = __( 'Home URL', 'wp-simple-firewall' );
				$sSummary = __( 'Plugin Home Page URL', 'wp-simple-firewall' );
				$sDescription = __( "When a user clicks the home link for this plugin, this is where they'll be directed.", 'wp-simple-firewall' );
				break;
			case 'wl_menuiconurl' :
				$sName = __( 'Menu Icon', 'wp-simple-firewall' );
				$sSummary = __( 'Menu Icon URL', 'wp-simple-firewall' );
				$sDescription = __( 'The URL of the icon to display in the menu.', 'wp-simple-firewall' )
								.' '.sprintf( __( 'The %s should measure %s.', 'wp-simple-firewall' ), __( 'icon', 'wp-simple-firewall' ), '16px x 16px' );
				break;
			case 'wl_dashboardlogourl' :
				$sName = __( 'Dashboard Logo', 'wp-simple-firewall' );
				$sSummary = __( 'Dashboard Logo URL', 'wp-simple-firewall' );
				$sDescription = __( 'The URL of the logo to display in the admin pages.', 'wp-simple-firewall' )
								.' '.sprintf( __( 'The %s should measure %s.', 'wp-simple-firewall' ), __( 'logo', 'wp-simple-firewall' ), '128px x 128px' );
				break;
			case 'wl_login2fa_logourl' :
				$sName = __( '2FA Login Logo URL', 'wp-simple-firewall' );
				$sSummary = __( '2FA Login Logo URL', 'wp-simple-firewall' );
				$sDescription = __( 'The URL of the logo to display on the Two-Factor Authentication login page.', 'wp-simple-firewall' );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}