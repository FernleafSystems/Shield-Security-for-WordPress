<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'block_anonymous_restapi' => [
				__( 'Blocked Anonymous API Access through "%s" namespace', 'wp-simple-firewall' )
			],
		];
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( $sSectionSlug ) {

		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_wordpress_lockdown' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMod()
																						 ->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Lockdown helps secure-up certain loosely-controlled WordPress settings on your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Lockdown', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_apixml' :
				$sTitle = __( 'API & XML-RPC', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Lockdown certain core WordPress system features.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'This depends on your usage and needs for certain WordPress functions and features.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'API & XML-RPC', 'wp-simple-firewall' );
				break;

			case 'section_permission_access_options' :
				$sTitle = __( 'Permissions and Access Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control of certain WordPress permissions.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Only enable SSL if you have a valid certificate installed.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Permissions', 'wp-simple-firewall' );
				break;

			case 'section_wordpress_obscurity_options' :
				$sTitle = __( 'WordPress Obscurity Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Obscures certain WordPress settings from public view.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Obscurity is not true security and so these settings are down to your personal tastes.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Obscurity', 'wp-simple-firewall' );
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

		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $sOptKey ) {

			case 'enable_lockdown' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'disable_xmlrpc' :
				$sName = sprintf( __( 'Disable %s', 'wp-simple-firewall' ), 'XML-RPC' );
				$sSummary = sprintf( __( 'Disable The %s System', 'wp-simple-firewall' ), 'XML-RPC' );
				$sDescription = sprintf( __( 'Checking this option will completely turn off the whole %s system.', 'wp-simple-firewall' ), 'XML-RPC' );
				break;

			case 'disable_anonymous_restapi' :
				$sName = __( 'Anonymous Rest API', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Disable The %s System', 'wp-simple-firewall' ), __( 'Anonymous Rest API', 'wp-simple-firewall' ) );
				$sDescription = [
					__( 'You can choose to completely disable anonymous access to the REST API.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Enabling this option may break plugins that use the REST API for your site visitors.', 'wp-simple-firewall' ) )
				];
				break;

			case 'api_namespace_exclusions' :
				$sName = __( 'Rest API Exclusions', 'wp-simple-firewall' );
				$sSummary = __( 'Anonymous REST API Exclusions', 'wp-simple-firewall' );
				$sDescription = __( 'Any namespaces provided here will be excluded from the Anonymous API restriction.', 'wp-simple-firewall' );
				break;

			case 'disable_file_editing' :
				$sName = __( 'Disable File Editing', 'wp-simple-firewall' );
				$sSummary = __( 'Disable Ability To Edit Files From Within WordPress', 'wp-simple-firewall' );
				$sDescription = __( 'Removes the option to directly edit any files from within the WordPress admin area.', 'wp-simple-firewall' )
								.'<br />'.__( 'Equivalent to setting "DISALLOW_FILE_EDIT" to TRUE.', 'wp-simple-firewall' );
				break;

			case 'force_ssl_admin' :
				$sName = __( 'Force SSL Admin', 'wp-simple-firewall' );
				$sSummary = __( 'Forces WordPress Admin Dashboard To Be Delivered Over SSL', 'wp-simple-firewall' );
				$sDescription = __( 'Please only enable this option if you have a valid SSL certificate installed.', 'wp-simple-firewall' )
								.'<br />'.__( 'Equivalent to setting "FORCE_SSL_ADMIN" to TRUE.', 'wp-simple-firewall' );
				break;

			case 'hide_wordpress_generator_tag' :
				$sName = __( 'WP Generator Tag', 'wp-simple-firewall' );
				$sSummary = __( 'Remove WP Generator Meta Tag', 'wp-simple-firewall' );
				$sDescription = __( 'Remove a meta tag from your WordPress pages that publicly displays that your site is WordPress and its current version.', 'wp-simple-firewall' );
				break;

			case 'block_author_discovery' :
				$sName = __( 'Block Username Fishing', 'wp-simple-firewall' );
				$sSummary = __( 'Block the ability to discover WordPress usernames based on author IDs', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'When enabled, any URL requests containing "%s" will be killed.', 'wp-simple-firewall' ), 'author=' )
								.'<br />'.sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Enabling this option may interfere with expected operations of your site.', 'wp-simple-firewall' ) );
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
}