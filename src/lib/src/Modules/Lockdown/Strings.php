<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'block_anonymous_restapi' => [
				'name'  => sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), __( 'Anonymous REST API' ) ),
				'audit' => [
					__( 'Blocked Anonymous API Access through "{{namespace}}" namespace.', 'wp-simple-firewall' ),
				],
			],
			'block_xml'               => [
				'name'  => sprintf( '%s: %s', __( 'Blocked', 'wp-simple-firewall' ), __( 'XML-RPC' ) ),
				'audit' => [
					__( 'XML-RPC Request Blocked.', 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_wordpress_lockdown' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMod()
																						->getMainFeatureName() );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Lockdown helps secure-up certain loosely-controlled WordPress settings on your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Lockdown', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_apixml' :
				$title = __( 'API & XML-RPC', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Lockdown certain core WordPress system features.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'This depends on your usage and needs for certain WordPress functions and features.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'API & XML-RPC', 'wp-simple-firewall' );
				break;

			case 'section_permission_access_options' :
				$title = __( 'Permissions and Access Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control of certain WordPress permissions.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Only enable SSL if you have a valid certificate installed.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Permissions', 'wp-simple-firewall' );
				break;

			case 'section_wordpress_obscurity_options' :
				$title = __( 'WordPress Obscurity Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Obscures certain WordPress settings from public view.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Obscurity is not true security and so these settings are down to your personal tastes.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Obscurity', 'wp-simple-firewall' );
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

		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_lockdown' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$description = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'disable_xmlrpc' :
				$name = sprintf( __( 'Disable %s', 'wp-simple-firewall' ), 'XML-RPC' );
				$summary = sprintf( __( 'Disable The %s System', 'wp-simple-firewall' ), 'XML-RPC' );
				$description = sprintf( __( 'Checking this option will completely turn off the whole %s system.', 'wp-simple-firewall' ), 'XML-RPC' );
				break;

			case 'disable_anonymous_restapi' :
				$name = __( 'Anonymous Rest API', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Disable The %s System', 'wp-simple-firewall' ), __( 'Anonymous Rest API', 'wp-simple-firewall' ) );
				$description = [
					__( 'You can completely disable anonymous access to the REST API.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Enabling this option may break plugins that use the REST API for your site visitors.', 'wp-simple-firewall' ) ),
					__( 'Use the exclusions option to allow anonymous access to specific API endpoints.', 'wp-simple-firewall' ),
				];
				break;

			case 'api_namespace_exclusions' :
				$name = __( 'Rest API Exclusions', 'wp-simple-firewall' );
				$summary = __( 'Anonymous REST API Exclusions', 'wp-simple-firewall' );
				$description = [
					__( 'These REST API namespaces will be excluded from the Anonymous API restriction.', 'wp-simple-firewall' ),
					sprintf( __( 'Some plugins (e.g. %s) use the REST API anonymously so you need to provide exclusions for them to work correctly.', 'wp-simple-firewall' ),
						'Contact Form 7' ),
					__( "Please contact the developer of a plugin to ask them for their REST API namespace if you need it." ),
					__( 'Some common namespaces' ).':',
				];
				
				$defaultEx = [
					'contact-form-7' => 'Contact Form 7',
					'tribe'          => 'The Events Calendar',
					'jetpack'        => 'JetPack',
					'woocommerce'    => 'WooCommerce',
					'wpstatistics'   => 'WP Statistics',
				];
				foreach ( $defaultEx as $defNamespace => $defName ) {
					$description[] = sprintf( '<code>%s</code> - %s', $defNamespace, $defName );
				}
				break;

			case 'disable_file_editing' :
				$name = __( 'Disable File Editing', 'wp-simple-firewall' );
				$summary = __( 'Disable Ability To Edit Files From Within WordPress', 'wp-simple-firewall' );
				$description = [
					__( 'Removes the option to directly edit any files from within the WordPress admin area.', 'wp-simple-firewall' ),
					__( 'Equivalent to setting "DISALLOW_FILE_EDIT" to TRUE.', 'wp-simple-firewall' )
				];
				break;

			case 'force_ssl_admin' :
				$name = __( 'Force SSL Admin', 'wp-simple-firewall' );
				$summary = __( 'Forces WordPress Admin Dashboard To Be Delivered Over SSL', 'wp-simple-firewall' );
				$description = [
					__( 'Please only enable this option if you have a valid SSL certificate installed.', 'wp-simple-firewall' ),
					__( 'Equivalent to setting "FORCE_SSL_ADMIN" to TRUE.', 'wp-simple-firewall' )
				];
				break;

			case 'hide_wordpress_generator_tag' :
				$name = __( 'WP Generator Tag', 'wp-simple-firewall' );
				$summary = __( 'Remove WP Generator Meta Tag', 'wp-simple-firewall' );
				$description = __( 'Remove a meta tag from your WordPress pages that publicly displays that your site is WordPress and its current version.', 'wp-simple-firewall' );
				break;

			case 'clean_wp_rubbish' :
				$name = __( 'Clean WP Files', 'wp-simple-firewall' );
				$summary = __( 'Automatically Delete Unnecessary WP Files', 'wp-simple-firewall' );
				$description = [
					__( "Automatically delete WordPress files that aren't necessary.", 'wp-simple-firewall' ),
					__( "The cleanup process runs once each day.", 'wp-simple-firewall' ),
					sprintf( '%s: <code>%s</code>', __( 'Files Deleted', 'wp-simple-firewall' ),
						implode( '</code><code>', [ 'wp-config-sample.php', 'readme.html', 'license.txt' ] ) )
				];
				break;

			case 'block_author_discovery' :
				$name = __( 'Block Username Fishing', 'wp-simple-firewall' );
				$summary = __( 'Block the ability to discover WordPress usernames based on author IDs', 'wp-simple-firewall' );
				$description = [
					sprintf( __( 'When enabled, any URL requests containing "%s" will be killed.', 'wp-simple-firewall' ), 'author=' ),
					sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Enabling this option may interfere with expected operations of your site.', 'wp-simple-firewall' ) )
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