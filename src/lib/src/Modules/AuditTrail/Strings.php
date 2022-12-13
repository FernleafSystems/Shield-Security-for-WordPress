<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'plugin_activated'            => [
				'name'  => __( 'Plugin Activated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was activated.', 'wp-simple-firewall' )
				],
			],
			'plugin_installed'            => [
				'name'  => __( 'Plugin Installed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was installed.', 'wp-simple-firewall' )
				],
			],
			'plugin_deactivated'          => [
				'name'  => __( 'Plugin Deactivated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was deactivated.', 'wp-simple-firewall' )
				],
			],
			'plugin_upgraded'             => [
				'name'  => __( 'Plugin Upgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Plugin "{{plugin}}" was upgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' )
				],
			],
			'plugin_file_edited'          => [
				'name'  => __( 'Plugin File Edited', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to edit the plugin file "{{file}}" directly through the WordPress editor.', 'wp-simple-firewall' )
				],
			],
			'theme_activated'             => [
				'name'  => __( 'Theme Activated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was activated.', 'wp-simple-firewall' ),
				],
			],
			'theme_installed'             => [
				'name'  => __( 'Theme Installed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was installed.', 'wp-simple-firewall' )
				],
			],
			'theme_file_edited'           => [
				'name'  => __( 'Theme File Edited', 'wp-simple-firewall' ),
				'audit' => [
					__( 'An attempt was made to edit the theme file "{{file}}" directly through the WordPress editor.', 'wp-simple-firewall' ),
				],
			],
			'theme_upgraded'              => [
				'name'  => __( 'Theme Upgraded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Theme "{{theme}}" was upgraded from version {{from}} to version {{to}}.', 'wp-simple-firewall' ),
				],
			],
			'core_updated'                => [
				'name'  => __( 'WP Core Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Core was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'permalinks_structure'        => [
				'name'  => __( 'Permalinks Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Permalinks Structure was updated from "{{from}}" to "{{to}}".', 'wp-simple-firewall' ),
				],
			],
			'post_deleted'                => [
				'name'  => __( 'Post Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress Post entitled "{{title}}" was permanently deleted from trash.', 'wp-simple-firewall' )
				],
			],
			'post_trashed'                => [
				'name'  => __( 'Post Trashed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was trashed.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_recovered'              => [
				'name'  => __( 'Post Recovered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was recovered from trash.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_updated'                => [
				'name'  => __( 'Post Updated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was updated.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_published'              => [
				'name'  => __( 'Post Published', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was published.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'post_unpublished'            => [
				'name'  => __( 'Post Unpublished', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Post entitled "{{title}}" was unpublished.', 'wp-simple-firewall' ),
					__( 'Post Type: {{type}}' ),
				],
			],
			'user_login'                  => [
				'name'  => __( 'User Login', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempted user login by "{{user_login}}" was successful.', 'wp-simple-firewall' ),
				],
			],
			'user_registered'             => [
				'name'  => __( 'User Registered', 'wp-simple-firewall' ),
				'audit' => [
					__( 'New WordPress user registered.', 'wp-simple-firewall' ),
					__( 'New username is "{{user_login}}" with email address "{{email}}".', 'wp-simple-firewall' ),
				],
			],
			'user_deleted'                => [
				'name'  => __( 'User Deleted', 'wp-simple-firewall' ),
				'audit' => [
					__( 'WordPress user deleted.', 'wp-simple-firewall' ),
					__( 'Username was "{{user_login}}" with email address "{{email}}".', 'wp-simple-firewall' ),
				],
			],
			'user_deleted_reassigned'     => [
				'name'  => __( 'User Deleted And Reassigned', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Deleted user posts were reassigned to user "{{user_login}}".', 'wp-simple-firewall' )
				],
			],
			'email_attempt_send'          => [
				'name'  => __( 'Email Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'There was an attempt to send an email using the "wp_mail" function.', 'wp-simple-firewall' ),
					__( "This log entry doesn't mean it was sent or received successfully, but only that an attempt was made.", 'wp-simple-firewall' ),
					__( 'It was sent to "{{to}}" with the subject "{{subject}}".', 'wp-simple-firewall' ),
					"CC/BCC Recipients: {{cc}} / {{bcc}}",
					__( 'The "wp_mail" function was called from the file "{{bt_file}}" on line {{bt_line}}.', 'wp-simple-firewall' )
				],
			],
			'user_login_app'              => [
				'name'  => __( 'User Login By App Password', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempted login by "{{user_login}}" using application password was successful.', 'wp-simple-firewall' ),
				],
			],
			'app_pass_created'            => [
				'name'  => __( 'APP Password Created', 'wp-simple-firewall' ),
				'audit' => [
					__( 'A new application password ({{app_pass_name}}) was created for user {{user_login}}.', 'wp-simple-firewall' ),
				],
			],
			'app_invalid_email'           => [
				'name'  => __( 'APP Password Auth - Invalid Email', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate App Password with invalid email.', 'wp-simple-firewall' ),
				],
			],
			'app_invalid_username'        => [
				'name'  => __( 'APP Password Auth - Invalid Username', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate App Password with invalid username.', 'wp-simple-firewall' ),
				],
			],
			'app_incorrect_password'      => [
				'name'  => __( 'Incorrect APP Password', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Attempt to authenticate with incorrect App Password.', 'wp-simple-firewall' ),
				],
			],
			'app_passwords_disabled'      => [
				'name'  => __( 'App Passwords Disabled', 'wp-simple-firewall' ),
				'audit' => [
					__( "Attempt to authenticate with App Password when they're disabled.", 'wp-simple-firewall' ),
				],
			],
			'app_passwords_disabled_user' => [
				'name'  => __( 'App Passwords Disabled For User', 'wp-simple-firewall' ),
				'audit' => [
					__( "Attempt to authenticate with App Password when they're disabled for the user.", 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * @inheritDoc
	 * @deprecated 16.2
	 */
	protected function getAdditionalDisplayStrings() :array {
		return [];
	}

	/**
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_audit_trail' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ),
					$this->getMod()->getMainFeatureName() );
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
		$con = $this->getCon();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$modName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_audit_trail' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$description = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;

			case 'log_level_db' :
				$name = __( 'Logging Level', 'wp-simple-firewall' );
				$summary = __( 'Logging Level For DB-Based Logs', 'wp-simple-firewall' );
				$description = [
					__( 'Specify the logging levels when using the local database.', 'wp-simple-firewall' ),
					__( "Debug and Info logging should only be enabled when investigating specific problems.", 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$con->plugin_urls->adminTop( PluginURLs::NAV_DOCS ),
						__( 'View all event details and their assigned levels', 'wp-simple-firewall' )
					)
				];
				break;

			case 'audit_trail_auto_clean' :
				$name = __( 'Auto Clean', 'wp-simple-firewall' );
				$summary = __( 'Automatically Purge Activity Log Entries Older Than The Set Number Of Days', 'wp-simple-firewall' );
				$description = [
					__( 'Events older than the number of days specified will be automatically cleaned from the database.', 'wp-simple-firewall' )
				];
				if ( !$con->isPremiumActive() ) {
					$description[] = sprintf( __( 'Upgrade to PRO to increase limit beyond %s days.', 'wp-simple-firewall' ),
						'<code>'.$opts->getDef( 'max_free_days' ).'</code>' );
				}
				break;

			case 'log_level_file' :
				$name = __( 'File Logging Level', 'wp-simple-firewall' );
				$summary = __( 'Logging Level For File-Based Logs', 'wp-simple-firewall' );
				$description = [
					__( 'Specify the logging levels when using the local filesystem.', 'wp-simple-firewall' ),
					sprintf( '%s: <code>%s</code>',
						__( 'Log File Location', 'wp-simple-firewall' ),
						$opts->getLogFilePath()
					),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$con->plugin_urls->adminTop( PluginURLs::NAV_DOCS ),
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
			'description' => $description,
		];
	}
}