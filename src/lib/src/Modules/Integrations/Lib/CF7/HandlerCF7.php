<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\CF7;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class HandlerCF7 {

	use ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return defined( 'WPCF7_TEXT_DOMAIN' ) && WPCF7_TEXT_DOMAIN === 'contact-form-7'
			   && $this->getCon()->isPremiumActive()
			   && !empty( array_filter( $this->getConfig() ) );
	}

	protected function run() {
		add_filter( 'wpcf7_spam', function ( $wasSpam, $submission ) {

			$isSpam = false;

			$cfg = $this->getConfig();
			if ( !$wasSpam ) {
				if ( !$isSpam && $cfg[ 'antibot' ] ) {
					$isSpam = $this->checkAntiBotIsSPAM();
				}
				if ( !$isSpam && $cfg[ 'human' ] ) {
					$isSpam = $this->checkHumanIsSPAM();
				}
				$this->getCon()->fireEvent( 'contactform7_spam_'.( $isSpam ? 'pass' : 'fail' ) );
			}

			$isSpam = $wasSpam || $isSpam;
			if ( $isSpam && $cfg[ 'offense' ] ) {
				$this->getCon()->fireEvent( 'contactform7_spam' );
			}

			return $isSpam;
		}, 1000, 2 );
	}

	private function checkHumanIsSPAM() :bool {
		return false;
	}

	private function checkAntiBotIsSPAM() :bool {
		return !$this->getCon()
					 ->getModule_Plugin()
					 ->getHandlerAntibot()
					 ->verify();
	}

	private function getConfig() :array {
		$opts = $this->getOptions();
		$opt = is_array( $opts->getOpt( 'cf7', [] ) ) ?
			$opts->getOpt( 'cf7', [] ) : $opts->getOptDefault( 'cf7' );
		return array_merge(
			[
				'antibot' => false,
				'human'   => false,
				'offense' => false,
			],
			array_map( '__return_true', array_flip( $opt ) )
		);
	}
}