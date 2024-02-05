<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class Strings extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Strings {

	public function getEventStrings() :array {
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
		$opts = $this->opts();
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
						$con->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_DOCS ),
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
						$con->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_DOCS ),
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