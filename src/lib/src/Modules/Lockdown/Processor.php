<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Services\Services;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	public function runDailyCron() {
		( new Lib\CleanRubbish() )->execute();
	}

	public function onWpInit() {
		/** @var Options $opts */
		$opts = $this->opts();
		if ( !Services::WpUsers()->isUserLoggedIn() && $opts->isRestApiAnonymousAccessDisabled() ) {
			add_filter( 'rest_authentication_errors', [ $this, 'disableAnonymousRestApi' ], 99 );
		}
	}

	/**
	 * Understand that if $mCurrentStatus is null, no check has been made. If true, something has
	 * authenticated the request, and if WP_Error, then an error is already present
	 * @param \WP_Error|true|null $mStatus
	 * @return \WP_Error
	 */
	public function disableAnonymousRestApi( $mStatus ) {

		$namespace = Services::Rest()->getNamespace();
		if ( !empty( $namespace ) && $mStatus !== true && !is_wp_error( $mStatus )
			 && !self::con()->getModule_Lockdown()->isPermittedAnonRestApiNamespace( $namespace ) ) {

			$mStatus = new \WP_Error(
				'shield_block_anon_restapi',
				sprintf( __( 'Anonymous access to the WordPress Rest API has been restricted by %s.', 'wp-simple-firewall' ),
					self::con()->getHumanName() ),
				[ 'status' => rest_authorization_required_code() ] );

			self::con()->fireEvent(
				'block_anonymous_restapi',
				[ 'audit_params' => [ 'namespace' => $namespace ] ]
			);
		}

		return $mStatus;
	}
}