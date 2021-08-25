<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'plugin_activated'        => [
				'name'  => __( 'Plugin Activated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was activated.', 'wp-simple-firewall' )
				],
			],
			'plugin_deactivated'      => [
				'name'  => __( 'Plugin Deactivated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was deactivated.', 'wp-simple-firewall' )
				],
			],
			'plugin_upgraded'         => [
				'name'  => __( 'Plugin Upgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was upgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' )
				],
			],
			'plugin_file_edited'      => [
				'name'  => __( 'Plugin File Edited', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to edit the plugin file "{{file}}" directly through the WordPress editor.', 'wp-simple-firewall' )
				],
			],
			'theme_activated'         => [
				'name'  => __( 'Theme Activated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was activated.', 'wp-simple-firewall' ),
				],
			],
			'theme_file_edited'       => [
				'name'  => __( 'Theme File Edited', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to edit the theme file "{{file}}" directly through the WordPress editor.', 'wp-simple-firewall' ),
				],
			],
			'theme_upgraded'          => [
				'name'  => __( 'Theme Upgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was upgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'core_updated'            => [
				'name'  => __( 'WP Core Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Core was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'permalinks_structure'    => [
				'name'  => __( 'Permalinks Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Permalinks Structure was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'post_deleted'            => [
				'name'  => __( 'Post Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Post entitled "{{title}}" was permanently deleted from trash.', 'wp-simple-firewall' )
				],
			],
			'post_trashed'            => [
				'name'  => __( 'Post Trashed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was trashed.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_recovered'          => [
				'name'  => __( 'Post Recovered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was recovered from trash.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated'            => [
				'name'  => __( 'Post Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was updated.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_published'          => [
				'name'  => __( 'Post Published', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was published.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_unpublished'        => [
				'name'  => __( 'Post Unpublished', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was unpublished.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'user_login'              => [
				'name'  => __( 'User Login', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempted user login by "{{user}}" was successful.', 'wp-simple-firewall' ),
				],
			],
			'user_login_app'          => [
				'name'  => __( 'User Login By App Password', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempted login by "{{user}}" using application password was successful.', 'wp-simple-firewall' ),
				],
			],
			'user_registered'         => [
				'name'  => __( 'User Registered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'New WordPress user registered.', 'wp-simple-firewall' ),
					__( 'New username is "{{user}}" with email address "{{email}}".', 'wp-simple-firewall' ),
				],
			],
			'user_deleted'            => [
				'name'  => __( 'User Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress user deleted.', 'wp-simple-firewall' ),
					__( 'Username was "{{user}}" with email address "{{email}}".', 'wp-simple-firewall' ),
				],
			],
			'user_deleted_reassigned' => [
				'name'  => __( 'User Deleted And Reassigned', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Deleted user posts were reassigned to user "{{user}}".', 'wp-simple-firewall' )
				],
			],
			'email_attempt_send'      => [
				'name'  => __( 'Email Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'There was an attempt to send an email using the "wp_mail" function.', 'wp-simple-firewall' ),
					__( "This log entry doesn't mean it was sent or received successfully, but only that an attempt was made.", 'wp-simple-firewall' ),
					__( 'It was sent to "{{to}}" with the subject "{{subject}}".', 'wp-simple-firewall' ),
					"CC/BCC Recipients: {{cc}} / {{bcc}}",
					__( 'The "wp_mail" function was called from the file "{{bt_file}}" on line {{bt_line}}.', 'wp-simple-firewall' )
				],
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getAdditionalDisplayStrings() :array {
		return [
			'at_users'            => __( 'Users', 'wp-simple-firewall' ),
			'at_plugins'          => __( 'Plugins', 'wp-simple-firewall' ),
			'at_themes'           => __( 'Themes', 'wp-simple-firewall' ),
			'at_wordpress'        => __( 'WordPress', 'wp-simple-firewall' ),
			'at_posts'            => __( 'Posts', 'wp-simple-firewall' ),
			'at_emails'           => __( 'Emails', 'wp-simple-firewall' ),
			'at_time'             => __( 'Time', 'wp-simple-firewall' ),
			'at_event'            => __( 'Event', 'wp-simple-firewall' ),
			'at_message'          => __( 'Message', 'wp-simple-firewall' ),
			'at_username'         => __( 'Username', 'wp-simple-firewall' ),
			'at_category'         => __( 'Category', 'wp-simple-firewall' ),
			'at_ipaddress'        => __( 'IP Address', 'wp-simple-firewall' ),
			'at_you'              => __( 'You', 'wp-simple-firewall' ),
			'at_no_audit_entries' => __( 'There are currently no audit entries in this section.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_audit_trail' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMod()
																						 ->getMainFeatureName() );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Audit Trail is designed so you can look back on events and analyse what happened and what may have gone wrong.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Audit Trail', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_localdb' :
				$title = __( 'Log To DB', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the audit trail itself.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Log To DB', 'wp-simple-firewall' );
				break;

			case 'section_enable_audit_contexts' :
				$title = __( 'Enable Audit Areas', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Specify which types of actions on your site are logged.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Audit Areas', 'wp-simple-firewall' );
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

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$con = $this->getCon();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_audit_trail' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$description = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'audit_trail_max_entries' :
				$name = __( 'Max Trail Length', 'wp-simple-firewall' );
				$summary = __( 'Maximum Audit Trail Length To Keep', 'wp-simple-firewall' );
				$description = [
					__( 'Automatically remove any audit trail entries when this limit is exceeded.', 'wp-simple-firewall' ),
				];
				if ( !$con->isPremiumActive() ) {
					$description[] = sprintf( __( 'Upgrade to PRO to increase limit above %s.', 'wp-simple-firewall' ),
						'<code>'.$opts->getDef( 'audit_trail_free_max_entries' ).'</code>' );
				}

				break;

			case 'log_level_db' :
				$name = __( 'Logging Level', 'wp-simple-firewall' );
				$summary = __( 'Logging Level For DB-Based Logs', 'wp-simple-firewall' );
				$description = [
					__( 'Specify the minimum logging level when using the local database.', 'wp-simple-firewall' ),
					__( 'Any selected level automatically includes all higher levels.', 'wp-simple-firewall' )
					.' e.g. '.__( 'Selecting "Info" includes "Info", "Warning" and "Alert" levels.', 'wp-simple-firewall' ),
					sprintf( '%s - %s',
						__( 'Recommendation', 'wp-simple-firewall' ),
						__( "Database logging should really only include Alerts and Warnings.", 'wp-simple-firewall' )
					),
					__( "Debug and Info logging would only be enabled when investigating specific problems.", 'wp-simple-firewall' )
				];
				break;

			case 'audit_trail_auto_clean' :
				$name = __( 'Auto Clean', 'wp-simple-firewall' );
				$summary = __( 'Automatically Purge Audit Log Entries Older Than The Set Number Of Days', 'wp-simple-firewall' );
				$description = __( 'Events older than the number of days specified will be automatically cleaned from the database.', 'wp-simple-firewall' );
				break;

			case 'enable_audit_context_users' :
				$name = __( 'Users And Logins', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Users And Logins', 'wp-simple-firewall' ) );
				$description = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'Users And Logins', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_plugins' :
				$name = __( 'Plugins', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Plugins', 'wp-simple-firewall' ) );
				$description = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'WordPress Plugins', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_themes' :
				$name = __( 'Themes', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Themes', 'wp-simple-firewall' ) );
				$description = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'WordPress Themes', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_posts' :
				$name = __( 'Posts And Pages', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Posts And Pages', 'wp-simple-firewall' ) );
				$description = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'Editing and publishing of posts and pages', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_wordpress' :
				$name = __( 'WordPress And Settings', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'WordPress And Settings', 'wp-simple-firewall' ) );
				$description = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'WordPress upgrades and changes to particular WordPress settings', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_emails' :
				$name = __( 'Emails', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), __( 'Emails', 'wp-simple-firewall' ) );
				$description = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), __( 'Email Sending', 'wp-simple-firewall' ) );
				break;

			case 'enable_audit_context_wpsf' :
				$name = $con->getHumanName();
				$summary = sprintf( __( 'Enable Audit Context - %s', 'wp-simple-firewall' ), $con->getHumanName() );
				$description = sprintf( __( 'When this context is enabled, the audit trail will track activity relating to: %s', 'wp-simple-firewall' ), $con->getHumanName() );
				break;

			case 'enable_change_tracking' :
				$name = __( 'Site Change Tracking', 'wp-simple-firewall' );
				$summary = __( 'Track Major Changes To Your Site', 'wp-simple-firewall' );
				$description = __( 'Tracking major changes to your site will help you monitor and catch malicious damage.', 'wp-simple-firewall' );
				break;

			case 'ct_snapshots_per_week' :
				$name = __( 'Snapshot Per Week', 'wp-simple-firewall' );
				$summary = __( 'Number Of Snapshots To Take Per Week', 'wp-simple-firewall' );
				$description = __( 'The number of snapshots to take per week. For daily snapshots, select 7.', 'wp-simple-firewall' )
							   .'<br />'.__( 'Data storage in your database increases with the number of snapshots.', 'wp-simple-firewall' )
							   .'<br />'.__( 'However, increased snapshots provide more granular information on when major site changes occurred.', 'wp-simple-firewall' );
				break;

			case 'ct_max_snapshots' :
				$name = __( 'Max Snapshots', 'wp-simple-firewall' );
				$summary = __( 'Maximum Number Of Snapshots To Retain', 'wp-simple-firewall' );
				$description = __( 'The more snapshots you retain, the further back you can look at changes over your site.', 'wp-simple-firewall' )
							   .'<br />'.__( 'You will need to consider the implications to database storage requirements.', 'wp-simple-firewall' );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $description,
		];
	}
}