<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	private $xmlProcessed = false;

	protected function run() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( $opts->isOptFileEditingDisabled() ) {
			$this->blockFileEditing();
		}

		if ( $opts->isOpt( 'force_ssl_admin', 'Y' ) && function_exists( 'force_ssl_admin' ) ) {
			if ( !defined( 'FORCE_SSL_ADMIN' ) ) {
				define( 'FORCE_SSL_ADMIN', true );
			}
			force_ssl_admin( true );
		}

		if ( $opts->isOpt( 'hide_wordpress_generator_tag', 'Y' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
		}

		if ( $opts->isXmlrpcDisabled() ) {
			add_filter( 'xmlrpc_enabled', [ $this, 'disableXmlrpc' ], 1000, 0 );
			add_filter( 'xmlrpc_methods', [ $this, 'disableXmlrpc' ], 1000, 0 );
		}
	}

	public function runDailyCron() {
		( new Lib\CleanRubbish() )
			->setMod( $this->getMod() )
			->execute();
	}

	private function blockFileEditing() {
		if ( !defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		add_filter( 'user_has_cap',
			/**
			 * @param array $allCaps
			 * @param array $cap
			 * @param array $args
			 * @return array
			 */
			function ( $allCaps, $cap, $args ) {
				$requestedCapability = $args[ 0 ];
				if ( in_array( $requestedCapability, [ 'edit_themes', 'edit_plugins', 'edit_files' ] ) ) {
					$allCaps[ $requestedCapability ] = false;
				}
				return $allCaps;
			},
			PHP_INT_MAX, 3
		);
	}

	public function onWpInit() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( !Services::WpUsers()->isUserLoggedIn() ) {
			$this->interceptCanonicalRedirects();
			if ( $opts->isRestApiAnonymousAccessDisabled() ) {
				add_filter( 'rest_authentication_errors', [ $this, 'disableAnonymousRestApi' ], 99 );
			}
		}
	}

	/**
	 * @return array|false
	 */
	public function disableXmlrpc() {
		if ( !$this->xmlProcessed ) {
			$this->xmlProcessed = true;
			$this->getCon()->fireEvent( 'block_xml' );
		}
		return ( current_filter() == 'xmlrpc_enabled' ) ? false : [];
	}

	/**
	 * @uses wp_die()
	 */
	private function interceptCanonicalRedirects() {

		if ( $this->getOptions()->isOpt( 'block_author_discovery', 'Y' ) ) {
			$author = Services::Request()->query( 'author', '' );
			if ( !empty( $author ) ) {
				Services::WpGeneral()->wpDie( sprintf(
					__( 'The "author" query parameter has been blocked by %s to protect against user login name fishing.', 'wp-simple-firewall' )
					.sprintf( '<br /><a href="%s" target="_blank">%s</a>',
						'https://shsec.io/7l',
						__( 'Learn More.', 'wp-simple-firewall' )
					),
					$this->getCon()->getHumanName()
				) );
			}
		}
	}

	/**
	 * Understand that if $mCurrentStatus is null, no check has been made. If true, something has
	 * authenticated the request, and if WP_Error, then an error is already present
	 * @param \WP_Error|true|null $mStatus
	 * @return \WP_Error
	 */
	public function disableAnonymousRestApi( $mStatus ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$WPRest = Services::Rest();

		$namespace = $WPRest->getNamespace();
		if ( !empty( $namespace ) && $mStatus !== true && !is_wp_error( $mStatus )
			 && !$mod->isPermittedAnonRestApiNamespace( $namespace ) ) {

			$mStatus = new \WP_Error(
				'shield_block_anon_restapi',
				sprintf( __( 'Anonymous access to the WordPress Rest API has been restricted by %s.', 'wp-simple-firewall' ), $this->getCon()
																																   ->getHumanName() ),
				[ 'status' => rest_authorization_required_code() ] );

			$this->getCon()
				 ->fireEvent(
					 'block_anonymous_restapi',
					 [ 'audit_params' => [ 'namespace' => $namespace ] ]
				 );
		}

		return $mStatus;
	}
}