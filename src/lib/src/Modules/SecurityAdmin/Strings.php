<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	public function getEventStrings() :array {
		return [
			'key_success'          => [
				'name'  => __( 'Security PIN Pass', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Security PIN authentication successful.', 'wp-simple-firewall' ),
				],
			],
			'key_fail'             => [
				'name'  => __( 'Security PIN Fail', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Security PIN authentication failed.', 'wp-simple-firewall' ),
				],
			],
			'attempt_deactivation' => [
				'name'  => __( 'Unauthorized Deactivation Attempt', 'wp-simple-firewall' ),
				'audit' => [
					sprintf( __( 'An attempt to deactivate the %s plugin by a non-admin was intercepted.', 'wp-simple-firewall' ),
						self::con()->getHumanName() ),
				],
			],
		];
	}

	public function getSectionStrings( string $section ) :array {
		$name = self::con()->getHumanName();

		switch ( $section ) {

			case 'section_enable_plugin_feature_admin_access_restriction' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to this plugin preventing unauthorized changes to your security settings.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) ) ),
					__( 'You need to also enter a new Security PIN to enable this feature.', 'wp-simple-firewall' ),
				];
				break;

			case 'section_security_admin_settings' :
				$title = __( 'Security Admin Restriction Settings', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to this plugin preventing unauthorized changes to your security settings.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				$titleShort = __( 'Security Admin Settings', 'wp-simple-firewall' );
				break;

			case 'section_admin_access_restriction_areas' :
				$title = __( 'Security Admin Restriction Zones', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to key WordPress areas for all users not authenticated with the Security Admin Access system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				$titleShort = __( 'Access Restriction Zones', 'wp-simple-firewall' );
				break;

			case 'section_whitelabel' :
				$title = __( 'White Label', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s',
						__( 'Purpose', 'wp-simple-firewall' ),
						sprintf( __( 'Rename and re-brand the %s plugin for your client site installations.', 'wp-simple-firewall' ),
							$name )
					),
					sprintf( '%s - %s',
						__( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'The Security Admin system must be active for these settings to apply.', 'wp-simple-firewall' ),
							$name )
					)
				];
				$titleShort = __( 'White Label', 'wp-simple-firewall' );
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
		/** @var Options $opts */
		$opts = $this->getOptions();
		$plugName = self::con()->getHumanName();

		switch ( $key ) {

			case 'enable_admin_access_restriction' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) );
				$summary = __( 'Enforce Security Admin Access Restriction', 'wp-simple-firewall' );
				$desc = [ __( "Enable this with great care and consideration. Ensure that you set an Security PIN that you'll remember.", 'wp-simple-firewall' ) ];
				break;

			case 'admin_access_key' :
				$name = __( 'Security Admin PIN', 'wp-simple-firewall' );
				$summary = __( 'Provide/Update Security Admin PIN', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'If you forget this, you could potentially lock yourself out from using this plugin.', 'wp-simple-firewall' ) ),
					'<strong>'.( $opts->hasSecurityPIN() ? __( 'Security PIN Currently Set', 'wp-simple-firewall' ) : __( 'Security PIN NOT Currently Set', 'wp-simple-firewall' ) ).'</strong>',
				];
				break;

			case 'sec_admin_users' :
				$name = __( 'Security Admins', 'wp-simple-firewall' );
				$summary = __( 'Persistent Security Admins', 'wp-simple-firewall' );
				$desc = [
					__( "Users provided will be security admins automatically, without needing the security PIN.", 'wp-simple-firewall' ),
					__( 'Enter admin username, email or ID.', 'wp-simple-firewall' ).' '.__( '1 entry per-line.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Verified users will be converted to usernames.', 'wp-simple-firewall' ) )
				];
				break;

			case 'admin_access_timeout' :
				$name = __( 'Security Admin Timeout', 'wp-simple-firewall' );
				$summary = __( 'Specify An Automatic Timeout Interval For Security Admin Access', 'wp-simple-firewall' );
				$desc = [
					__( 'This will automatically expire your Security Admin Session.', 'wp-simple-firewall' ),
					sprintf(
						'%s: %s',
						__( 'Default', 'wp-simple-firewall' ),
						sprintf( '%s minutes', $opts->getOptDefault( 'admin_access_timeout' ) )
					)
				];
				break;

			case 'allow_email_override' :
				$name = __( 'Allow Email Override', 'wp-simple-firewall' );
				$summary = __( 'Allow Email Override Of Admin Access Restrictions', 'wp-simple-firewall' );
				$desc = [
					__( 'Allow the use of verification emails to override and switch off the Security Admin restrictions.', 'wp-simple-firewall' ),
					sprintf( __( "The email address specified in %s's General settings will be used.", 'wp-simple-firewall' ), $plugName )
				];
				break;

			case 'enable_mu' :
				$name = __( 'Run In MU Mode', 'wp-simple-firewall' );
				$summary = __( 'Run Plugin In Must-Use (MU) Mode', 'wp-simple-firewall' );
				$desc = [
					__( "Setup the plugin to run as an MU-plugin to prevent accidental deactivation.", 'wp-simple-firewall' ),
					__( "You should fully understand the implications of this, including the inability to deactivate the plugin from the WordPress dashboard while this setting is active.", 'wp-simple-firewall' ),
					sprintf( '<strong>%s</strong>: %s', __( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'WordPress must be at least version %s to activate this option.', 'wp-simple-firewall' ), '<code>5.6</code>' ) ),
				];
				break;

			case 'admin_access_restrict_posts' :
				$name = __( 'Pages', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To Key WordPress Posts And Pages Actions', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to page/post creation, editing and deletion.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ), __( 'Edit', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'admin_access_restrict_plugins' :
				$name = __( 'Plugins', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To Key WordPress Plugin Actions', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to plugin installation, update, activation and deletion.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ), __( 'Activate', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'admin_access_restrict_options' :
				$name = __( 'WordPress Options', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To Certain WordPress Admin Options', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict the ability of WordPress administrators from changing key WordPress settings.', 'wp-simple-firewall' ) ),
					__( 'The following options will be restricted:', 'wp-simple-firewall' ),
					sprintf( '<ul style="list-style-type: square"><li>%s</li></ul>', \implode( '</li><li>', [
						sprintf( '%s %s', __( 'New User Default Role' ), '<span class="badge bg-success">new</span>' ),
						sprintf( '%s %s', __( 'Permalink structure' ), '<span class="badge bg-success">new</span>' ),
						__( 'Site Title' ),
						__( 'Tagline' ),
						__( 'WordPress Address (URL)' ),
						__( 'Site Address (URL)' ),
						__( 'Administration Email Address' ),
						sprintf( '%s (%s)', __( 'Membership' ), __( 'Anyone can register' ) ),
						__( 'Email notifications for new comments', 'wp-simple-firewall' ),
						__( 'Comments must be manually approved' ),
						__( 'Search engine visibility' ),
					] ) )
				];
				break;

			case 'admin_access_restrict_admin_users' :
				$name = __( 'Admin Users', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To Create/Delete/Modify Other Admin Users', 'wp-simple-firewall' );
				$desc = [ sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict the ability of WordPress administrators from creating, modifying or promoting other administrators.', 'wp-simple-firewall' ) ) ];
				break;

			case 'admin_access_restrict_themes' :
				$name = __( 'Themes', 'wp-simple-firewall' );
				$summary = __( 'Restrict Access To WordPress Theme Actions', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ),
						__( 'This will restrict access to theme installation, update, activation and deletion.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
						sprintf(
							__( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ),
							sprintf(
								__( '%s and %s', 'wp-simple-firewall' ),
								__( 'Activate', 'wp-simple-firewall' ),
								__( 'Edit Theme Options', 'wp-simple-firewall' )
							)
						)
					)
				];
				break;

			case 'whitelabel_enable' :
				$name = sprintf( '%s: %s', __( 'Enable', 'wp-simple-firewall' ), __( 'White Label', 'wp-simple-firewall' ) );
				$summary = __( 'Activate Your White Label Settings', 'wp-simple-firewall' );
				$desc = [ __( 'Turn your White Label settings on/off.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_hide_updates' :
				$name = __( 'Hide Updates', 'wp-simple-firewall' );
				$summary = __( 'Hide Plugin Updates From Non-Security Admins', 'wp-simple-firewall' );
				$desc = [ sprintf( __( 'Hide available %s updates from non-security administrators.', 'wp-simple-firewall' ), $plugName ) ];
				break;
			case 'wl_pluginnamemain' :
				$name = __( 'Plugin Name', 'wp-simple-firewall' );
				$summary = __( 'The Name Of The Plugin', 'wp-simple-firewall' );
				$desc = [ __( 'The name of the plugin that will be displayed to your site users.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_replace_badge_url' :
				$name = __( 'Replace Plugin Badge', 'wp-simple-firewall' );
				$summary = __( 'Replace Plugin Badge URL and Images', 'wp-simple-firewall' );
				$desc = [ __( 'When using the plugin badge, replace the URL and link with your Whitelabel settings.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_namemenu' :
				$name = __( 'Menu Title', 'wp-simple-firewall' );
				$summary = __( 'The Main Menu Title Of The Plugin', 'wp-simple-firewall' );
				$desc = [ sprintf( __( 'The Main Menu Title Of The Plugin. If left empty, the "%s" will be used.', 'wp-simple-firewall' ), __( 'Plugin Name', 'wp-simple-firewall' ) ) ];
				break;
			case 'wl_companyname' :
				$name = __( 'Company Name', 'wp-simple-firewall' );
				$summary = __( 'The Name Of Your Company', 'wp-simple-firewall' );
				$desc = [ __( 'Provide the name of your company.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_description' :
				$name = __( 'Description', 'wp-simple-firewall' );
				$summary = __( 'The Description Of The Plugin', 'wp-simple-firewall' );
				$desc = [ __( 'The description of the plugin displayed on the plugins page.', 'wp-simple-firewall' ) ];
				break;
			case 'wl_homeurl' :
				$name = __( 'Home URL', 'wp-simple-firewall' );
				$summary = __( 'Plugin Home Page URL', 'wp-simple-firewall' );
				$desc = [ __( "When a user clicks the home link for this plugin, this is where they'll be directed.", 'wp-simple-firewall' ) ];
				break;
			case 'wl_menuiconurl' :
				$name = __( 'Menu Icon', 'wp-simple-firewall' );
				$summary = __( 'Menu Icon URL', 'wp-simple-firewall' );
				$desc = [
					__( 'The URL of the icon to display in the menu.', 'wp-simple-firewall' ),
					sprintf( __( 'The %s should measure %s.', 'wp-simple-firewall' ), __( 'icon', 'wp-simple-firewall' ), '16px x 16px' )
				];
				break;
			case 'wl_dashboardlogourl' :
				$name = __( 'Plugin Badge Logo', 'wp-simple-firewall' );
				$summary = __( 'Plugin Badge Logo URL', 'wp-simple-firewall' );
				$desc = [
					__( 'The URL of the logo to display in the plugin badge.', 'wp-simple-firewall' ),
					sprintf( __( 'The %s should measure %s.', 'wp-simple-firewall' ), __( 'logo', 'wp-simple-firewall' ), '128px x 128px' )
				];
				break;
			case 'wl_login2fa_logourl' :
				$name = __( 'Dashboard and 2FA Login Logo URL', 'wp-simple-firewall' );
				$summary = __( 'Dashboard and 2FA Login Logo URL', 'wp-simple-firewall' );
				$desc = [ __( 'The URL of the logo to display on the Dashboard and the Two-Factor Authentication login page.', 'wp-simple-firewall' ) ];
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