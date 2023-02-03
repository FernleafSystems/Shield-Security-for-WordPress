<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	public function runDailyCron() {
		( new Lib\CleanRubbish() )
			->setMod( $this->getMod() )
			->execute();
	}

	public function onWpInit() {
		/** @var Options $opts */
		$opts = $this->getOptions();
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