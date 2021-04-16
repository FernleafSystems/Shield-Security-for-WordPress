<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Services\Services;

class MfaProfilesController {

	use MfaControllerConsumer;
	use ExecOnce;

	private $rendered = false;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$this->defineShortcodes();
		if ( Services::WpUsers()->isUserLoggedIn() ) {
			add_action( 'wp', function () {
				$this->enqueueAssets();
			} );
		}
	}

	private function enqueueAssets() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {

			$enqueues[ Enqueue::JS ][] = 'frontend/userprofile';

			add_filter( 'shield/custom_dequeues', function ( $assets ) {
				if ( !$this->rendered ) {
					$assets[ Enqueue::JS ][] = 'frontend/userprofile';
				}
				return $assets;
			} );

			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				$mfaCon = $this->getMfaCon();
				$providers = $mfaCon->getProvidersForUser( Services::WpUsers()->getCurrentWpUser() );
				if ( !empty( $providers ) ) {
					$localz[] = [
						'frontend/userprofile',
						'shield_vars_userprofile',
						[
							'vars' => [
								'providers' => array_map( function ( $provider ) {
									return $provider->getJavascriptVars();
								}, $providers )
							],
						]
					];
				}
				return $localz;
			} );

			return $enqueues;
		} );
	}

	private function loadUserProfileMFA( $attributes = [] ) :string {
		$this->rendered = true;
		return ( new Profiles\RenderCustomForms() )
			->setMfaController( $this->getMfaCon() )
			->setWpUser( Services::WpUsers()->getCurrentWpUser() )
			->render( is_array( $attributes ) ? $attributes : [] );
	}

	private function defineShortcodes() {
		add_shortcode( 'SHIELD_USER_PROFILE_MFA', function ( $attributes ) {
			return $this->loadUserProfileMFA( $attributes );
		} );
	}
}