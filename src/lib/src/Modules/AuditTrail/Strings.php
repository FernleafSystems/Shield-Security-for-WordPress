<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	public function getEventStrings() :array {
		return [
			'db_tables_added'              => [
				'name'  => __( 'DB Tables Added', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Database table(s) added:', 'wp-simple-firewall' ),
					'{{tables}}'
				],
			],
			'db_tables_removed'            => [
				'name'  => __( 'DB Tables Removed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Database table(s) removed:', 'wp-simple-firewall' ),
					'{{tables}}'
				],
			],
			'plugin_activated'             => [
				'name'  => __( 'Plugin Activated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was activated (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'plugin_installed'             => [
				'name'  => __( 'Plugin Installed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was installed (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'plugin_uninstalled'           => [
				'name'  => __( 'Plugin Uninstalled', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was uninstalled (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'plugin_deactivated'           => [
				'name'  => __( 'Plugin Deactivated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was deactivated (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'plugin_upgraded'              => [
				'name'  => __( 'Plugin Upgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" upgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' )
				],
			],
			'plugin_downgraded'            => [
				'name'  => __( 'Plugin Downgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" downgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' )
				],
			],
			'plugin_file_edited'           => [
				'name'  => __( 'Plugin File Edited', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to edit the plugin file "{{file}}" directly through the WordPress editor.', 'wp-simple-firewall' )
				],
			],
			'theme_activated'              => [
				'name'  => __( 'Theme Activated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was activated.', 'wp-simple-firewall' ),
				],
			],
			'theme_installed'              => [
				'name'  => __( 'Theme Installed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was installed.', 'wp-simple-firewall' )
				],
			],
			'theme_uninstalled'            => [
				'name'  => __( 'Theme Uninstalled', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was uninstalled (v{{version}}).', 'wp-simple-firewall' )
				],
			],
			'theme_file_edited'            => [
				'name'  => __( 'Theme File Edited', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to edit the theme file "{{file}}" directly through the WordPress editor.', 'wp-simple-firewall' ),
				],
			],
			'theme_upgraded'               => [
				'name'  => __( 'Theme Upgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was upgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'theme_downgraded'             => [
				'name'  => __( 'Theme Downgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was downgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'core_updated'                 => [
				'name'  => __( 'WP Core Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Core was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'permalinks_structure'         => [
				'name'  => __( 'Permalinks Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Permalinks Structure was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_admin_email'        => [
				'name'  => __( 'WP Site Admin Email', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress site admin email address was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_blogdescription'    => [
				'name'  => __( 'WP Site Tagline', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress site tagline was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_blogname'           => [
				'name'  => __( 'WP Site title', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress site title changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_default_role'       => [
				'name'  => __( 'WP Default User Role', 'wp-simple-firewall' ),
				'audit' => [
					__( 'The default role for new users was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_home'               => [
				'name'  => __( 'Home URL Changed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'The home URL was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_siteurl'            => [
				'name'  => __( 'Site URL Changed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'The site URL was changed from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'wp_option_users_can_register' => [
				'name'  => __( 'WP User Registration', 'wp-simple-firewall' ),
				'audit' => [
					__( 'The option to allow anyone to register on the site was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'post_deleted'                 => [
				'name'  => __( 'Post Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Post entitled "{{title}}" was permanently deleted from trash.', 'wp-simple-firewall' )
				],
			],
			'post_trashed'                 => [
				'name'  => __( 'Post Trashed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was trashed.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_recovered'               => [
				'name'  => __( 'Post Recovered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was recovered from trash.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated'                 => [
				'name'  => __( 'Post Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was updated.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_created'                 => [
				'name'  => __( 'Post Created', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was created.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_published'               => [
				'name'  => __( 'Post Published', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was published.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_unpublished'             => [
				'name'  => __( 'Post Unpublished', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was unpublished.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated_content'         => [
				'name'  => __( 'Post Content Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Content for Post ID {{post_id}} updated.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated_title'           => [
				'name'  => __( 'Post Title Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Title for Post ID {{post_id}} updated from "{{title_old}}" to "{{title_new}}".', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated_slug'            => [
				'name'  => __( 'Post Slug Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Slug for Post ID {{post_id}} updated from "{{slug_old}}" to "{{slug_new}}".', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'user_login'                   => [
				'name'  => __( 'User Login', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempted user login by "{{user_login}}" was successful.', 'wp-simple-firewall' ),
				],
			],
			'user_registered'              => [
				'name'  => __( 'User Registered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'New WordPress user registered.', 'wp-simple-firewall' ),
					__( 'New username is "{{user_login}}" with email address "{{email}}".', 'wp-simple-firewall' ),
				],
			],
			'user_deleted'                 => [
				'name'  => __( 'User Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress user deleted.', 'wp-simple-firewall' ),
					__( 'Username was "{{user_login}}" with email address "{{email}}".', 'wp-simple-firewall' ),
				],
			],
			'user_promoted'                => [
				'name'  => __( 'User Promoted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" promoted to administrator role.', 'wp-simple-firewall' ),
				],
			],
			'user_demoted'                 => [
				'name'  => __( 'User Demoted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'User "{{user_login}}" demoted from administrator role.', 'wp-simple-firewall' ),
				],
			],
			'user_email_updated'           => [
				'name'  => __( 'User Email Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Email updated for user "{{user_login}}".', 'wp-simple-firewall' )
				],
			],
			'user_password_updated'        => [
				'name'  => __( 'User Password Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Password updated for user "{{user_login}}".', 'wp-simple-firewall' )
				],
			],
			'user_deleted_reassigned'      => [
				'name'  => __( 'User Deleted And Reassigned', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Deleted user posts were reassigned to user "{{user_login}}".', 'wp-simple-firewall' )
				],
			],
			'email_attempt_send'           => [
				'name'  => __( 'Email Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'There was an attempt to send an email using the "wp_mail" function.', 'wp-simple-firewall' ),
					__( "This log entry doesn't mean it was sent or received successfully, but only that an attempt was made.", 'wp-simple-firewall' ),
					__( 'It was sent to "{{to}}" with the subject "{{subject}}".', 'wp-simple-firewall' ),
					"CC/BCC Recipients: {{cc}} / {{bcc}}",
					__( 'The "wp_mail" function was called from the file "{{bt_file}}" on line {{bt_line}}.', 'wp-simple-firewall' )
				],
			],
			'user_login_app'               => [
				'name'  => __( 'User Login By App Password', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempted login by "{{user_login}}" using application password was successful.', 'wp-simple-firewall' ),
				],
			],
			'app_pass_created'             => [
				'name'  => __( 'APP Password Created', 'wp-simple-firewall' ),
				'audit' => [
					__( 'A new application password ({{app_pass_name}}) was created for user {{user_login}}.', 'wp-simple-firewall' ),
				],
			],
			'app_invalid_email'            => [
				'name'  => __( 'APP Password Auth - Invalid Email', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate App Password with invalid email.', 'wp-simple-firewall' ),
				],
			],
			'app_invalid_username'         => [
				'name'  => __( 'APP Password Auth - Invalid Username', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate App Password with invalid username.', 'wp-simple-firewall' ),
				],
			],
			'app_incorrect_password'       => [
				'name'  => __( 'Incorrect APP Password', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate with incorrect App Password.', 'wp-simple-firewall' ),
				],
			],
			'app_passwords_disabled'       => [
				'name'  => __( 'App Passwords Disabled', 'wp-simple-firewall' ),
				'audit' => [
					__( "Attempt to authenticate with App Password when they're disabled.", 'wp-simple-firewall' ),
				],
			],
			'app_passwords_disabled_user'  => [
				'name'  => __( 'App Passwords Disabled For User', 'wp-simple-firewall' ),
				'audit' => [
					__( "Attempt to authenticate with App Password when they're disabled for the user.", 'wp-simple-firewall' ),
				],
			],
			'comment_created'              => [
				'name'  => __( 'New Comment', 'wp-simple-firewall' ),
				'audit' => [
					__( "Comment ID:{{comment_id}} with status '{{status}}' was newly created on Post ID {{post_id}}.", 'wp-simple-firewall' ),
				],
			],
			'comment_deleted'              => [
				'name'  => __( 'Comment Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( "Comment ID:{{comment_id}} (on Post ID {{post_id}}) with status '{{status}}' was permanently deleted.", 'wp-simple-firewall' ),
				],
			],
			'comment_status_updated'       => [
				'name'  => __( 'Comment Status Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( "Comment ID:{{comment_id}} (on Post ID {{post_id}}) changed status from '{{status_old}}' to '{{status_new}}'.", 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_audit_trail' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ),
					$this->mod()->getMainFeatureName() );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Activity Log is designed so you can look back on events and analyse what happened and what may have gone wrong.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Activity Log', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_localdb' :
				$title = __( 'Log To DB', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the Activity Log itself.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Log To DB', 'wp-simple-firewall' );
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
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$con = self::con();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$modName = $this->mod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_audit_trail' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;

			case 'log_level_db' :
				$name = __( 'Logging Level', 'wp-simple-firewall' );
				$summary = __( 'Logging Level For DB-Based Logs', 'wp-simple-firewall' );
				$desc = [
					__( 'Specify the logging levels when using the local database.', 'wp-simple-firewall' ),
					__( "Debug and Info logging should only be enabled when investigating specific problems.", 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$con->plugin_urls->adminTopNav( PluginNavs::NAV_DOCS ),
						__( 'View all event details and their assigned levels', 'wp-simple-firewall' )
					)
				];
				break;

			case 'audit_trail_auto_clean' :
				$name = __( 'Log Retention', 'wp-simple-firewall' );
				$summary = __( 'Automatically Purge Activity Logs Older Than The Set Number Of Days', 'wp-simple-firewall' );
				$desc = [
					__( 'Events older than the number of days specified will be automatically cleaned from the database.', 'wp-simple-firewall' )
				];
				if ( !$con->caps->hasCap( 'logs_retention_unlimited' ) ) {
					$desc[] = sprintf(
						__( 'The maximum log retention limit (%s) may be increased by upgrading your ShieldPRO plan.', 'wp-simple-firewall' ),
						$con->caps->getMaxLogRetentionDays()
					);
				}
				break;

			case 'log_level_file' :
				$name = __( 'File Logging Level', 'wp-simple-firewall' );
				$summary = __( 'Logging Level For File-Based Logs', 'wp-simple-firewall' );
				$desc = [
					__( 'Specify the logging levels when using the local filesystem.', 'wp-simple-firewall' ),
					sprintf( '%s: <code>%s</code>',
						__( 'Log File Location', 'wp-simple-firewall' ),
						$opts->getLogFilePath()
					),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$con->plugin_urls->adminTopNav( PluginNavs::NAV_DOCS ),
						__( 'View all event details and their assigned levels', 'wp-simple-firewall' )
					),
					sprintf( '%s: %s',
						__( 'Note', 'wp-simple-firewall' ),
						sprintf( __( 'Log files will be rotated daily up to a limit of %s.', 'wp-simple-firewall' ),
							sprintf( '<code>%s</code>', $opts->getLogFileRotationLimit() ) )
					)
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