<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Profiles;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\MfaControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BaseProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpUserConsumer;

class CustomForms {

	use ExecOnce;
	use MfaControllerConsumer;
	use WpUserConsumer;

	/**
	 * @var BaseProvider[]
	 */
	private $aWorkingProviders = [];

	public function run() {
		// Enqueue Javascript.
		add_action( 'wp_enqueue_scripts', function () {
			$this->enqueueAssets();
		}, 0 );
		add_action( 'wp_footer', function () {
			$this->maybeDequeueAssets();
		}, 0 );
		add_action( 'custom_profile_form_output', function ( array $aProviders ) {
			$this->renderCustomProfileFormOutput( $aProviders );
		} );
	}

	/**
	 * @param string[] $aPs - list of limited provider slugs
	 */
	private function renderCustomProfileFormOutput( array $aPs ) {
		$oMC = $this->getMfaCon();
		$user = $this->getWpUser();

		$aProviders = $oMC->getProvidersForUser( $user, true );
		if ( !empty( $aPs ) ) {
			$aProviders = array_filter(
				$aProviders,
				function ( $oP ) use ( $aPs ) {
					return in_array( $oP::SLUG, array_map( 'strtolower', $aPs ) );
				}
			);
		}

		if ( !empty( $aProviders ) ) {
			$this->aWorkingProviders = $aProviders;
			foreach ( $aProviders as $oP ) {
			}
		}
	}

	private function enqueueAssets() {
		//enqueue in footer
	}

	private function maybeDequeueAssets() {
		if ( empty( $this->aWorkingProviders ) ) {
			//dequeue
		}
	}
}