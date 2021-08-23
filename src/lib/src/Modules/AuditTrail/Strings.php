<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	protected function getAuditMessages() :array {
		return [
			'plugin_activated'        => [
				__( 'Plugin "%s" was activated.', 'wp-simple-firewall' )
			],
			'plugin_deactivated'      => [
				__( 'Plugin "%s" was deactivated.', 'wp-simple-firewall' )
			],
			'plugin_file_edited'      => [
				__( 'An attempt was made to edit the plugin file "%s" directly through the WordPress editor.', 'wp-simple-firewall' )
			],
			'plugin_upgraded'         => [
				__( 'Plugin "%s" was upgraded from version %s to version %s.', 'wp-simple-firewall' )
			],
			'theme_activated'         => [
				__( 'Theme "%s" was activated.', 'wp-simple-firewall' )
			],
			'theme_file_edited'       => [
				__( 'An attempt was made to edit the theme file "%s" directly through the WordPress editor.', 'wp-simple-firewall' )
			],
			'theme_upgraded'          => [
				__( 'Theme "%s" was upgraded from version %s to version %s.', 'wp-simple-firewall' )
			],
			'core_updated'            => [
				__( 'WordPress Core was updated from "%s" to "%s".', 'wp-simple-firewall' )
			],
			'permalinks_structure'    => [
				__( 'WordPress Permalinks Structure was updated from "%s" to "%s".', 'wp-simple-firewall' )
			],
			'post_deleted'            => [
				__( 'WordPress Post entitled "%s" was permanently deleted from trash.', 'wp-simple-firewall' )
			],
			'post_trashed'            => [
				__( 'Post entitled "%s" was trashed.', 'wp-simple-firewall' ),
				__( 'Post Type: %s' ),
			],
			'post_recovered'          => [
				__( 'Post entitled "%s" was recoverd from trash.', 'wp-simple-firewall' ),
				__( 'Post Type: %s' ),
			],
			'post_updated'            => [
				__( 'Post entitled "%s" was updated.', 'wp-simple-firewall' ),
				__( 'Post Type: %s' ),
			],
			'post_published'          => [
				__( 'Post entitled "%s" was published.', 'wp-simple-firewall' ),
				__( 'Post Type: %s' ),
			],
			'post_unpublished'        => [
				__( 'Post entitled "%s" was unpublished.', 'wp-simple-firewall' ),
				__( 'Post Type: %s' ),
			],
			'user_login'              => [
				__( 'Attempted user login by "%s" was successful.', 'wp-simple-firewall' ),
			],
			'user_login_app'          => [
				__( 'Attempted login by "%s" using application password was successful.', 'wp-simple-firewall' ),
			],
			'user_registered'         => [
				__( 'New WordPress user registered.', 'wp-simple-firewall' )
				.' '.__( 'New username is "%s" with email address "%s".', 'wp-simple-firewall' )
			],
			'user_deleted'            => [
				__( 'WordPress user deleted.', 'wp-simple-firewall' )
				.' '.__( 'Username was "%s" with email address "%s".', 'wp-simple-firewall' )
			],
			'user_deleted_reassigned' => [
				__( 'Deleted user posts were reassigned to user "%s".', 'wp-simple-firewall' )
			],
			'email_attempt_send'      => [
				__( 'There was an attempt to send an email using the "wp_mail" function.', 'wp-simple-firewall' ),
				__( "This log entry doesn't mean it was sent or received successfully, but only that an attempt was made.", 'wp-simple-firewall' ),
				__( 'It was sent to "%s" with the subject "%s".', 'wp-simple-firewall' ),
				"CC/BCC Recipients: %s / %s",
				__( 'The "wp_mail" function was called from the file "%s" on line %s.', 'wp-simple-firewall' )
			],
			'email_send_invalid'      => [
				__( 'Attempting to log email, but data was not of the correct type (%s)', 'wp-simple-firewall' ),
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
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMod()
																						 ->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Audit Trail is designed so you can look back on events and analyse what happened and what may have gone wrong.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Audit Trail', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_localdb' :
				$sTitle = __( 'Log To DB', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the audit trail itself.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Log To DB', 'wp-simple-firewall' );
				break;

			case 'section_enable_audit_contexts' :
				$sTitle = __( 'Enable Audit Areas', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Specify which types of actions on your site are logged.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Audit Areas', 'wp-simple-firewall' );
				break;

			/*
		case 'section_change_tracking' :
			$sTitle = __( 'Track All Major Changes To Your Site', 'wp-simple-firewall' );
			$sTitleShort = __( 'Change Tracking', 'wp-simple-firewall' );
			$aData = ( new Shield\ChangeTrack\Snapshot\Collate() )->run();
			$sResult = (int)( strlen( base64_encode( WP_Http_Encoding::compress( json_encode( $aData ) ) ) )/1024 );
			$aSummary = [
				sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Track significant changes to your site.', 'wp-simple-firewall' ) )
				.' '.sprintf( '%s - %s', __( 'Note', 'wp-simple-firewall' ), __( 'This is separate from the Audit Trail.', 'wp-simple-firewall' ) ),
				sprintf( '%s - %s', __( 'Considerations', 'wp-simple-firewall' ),
					__( 'Change Tracking uses snapshots that may use take up  lot of data.', 'wp-simple-firewall' )
					.' '.sprintf( 'Each snapshot will consume ~%sKB in your database', $sResult )
				),
			];
			break;
			*/

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