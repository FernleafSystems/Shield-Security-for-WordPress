<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\AuditTrail\Auditor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MouseTrap {

	use Auditor, ModConsumer;

	public function run() {
		add_action( 'init', [ $this, 'onWpInit' ] );
	}

	public function onWpInit() {
		if ( !Services::WpUsers()->isUserLoggedIn() ) {
			$this->checkForVenusRequest();
			add_filter( 'robots_txt', array( $this, 'appendRobotsTxt' ), 5 );
			add_action( 'wp_footer', array( $this, 'insertMouseTrap' ) );
		}
	}

	/**
	 * @param string $sRobotsText
	 * @return string
	 */
	public function appendRobotsTxt( $sRobotsText ) {
		if ( Services::WpGeneral()->isPermalinksEnabled() ) {
			$sRobotsText .= sprintf( "\nDisallow: %s", parse_url( $this->getTrapLink(), PHP_URL_PATH ) );
		}
		return $sRobotsText;
	}

	private function checkForVenusRequest() {
		if ( $this->isVenusRequest() ) {
			/** @var \ICWP_WPSF_FeatureHandler_Firewall $oFO */
			$oFO = $this->getMod();

			$sAuditMessage = _wpsf__( 'MouseTrap found visitor to be a bot.' );

			if ( $oFO->isMouseTrayBlock() ) {
				$oFO->setIpBlocked();
				$sAuditMessage .= ' '._wpsf__( 'IP set to be blocked.' );
			}
			else {
				$oFO->setIpTransgressed();
				$sAuditMessage .= ' '._wpsf__( 'Transgression counter set to increase.' );
			}

			$this->createNewAudit( $sAuditMessage, 2, 'mouse_trapped' );
			Services::WpGeneral()->wpDie( 'Did you really mean to come here?' );
		}
	}

	protected function isVenusRequest() {
		/** @var \ICWP_WPSF_FeatureHandler_Firewall $oFO */
		$oFO = $this->getMod();

		$oReq = Services::Request();
		if ( Services::WpGeneral()->isPermalinksEnabled() ) {
			$bVenus = trim( $oReq->getPath(), '/' ) == sprintf( '%s-%s', $oFO->prefix( 'mouse' ), $oFO->getMouseTrapKey() );
		}
		else {
			$bVenus = $oFO->prefix( $oReq->query( 'mouse' ) ) == 1;
		}
		return $bVenus;
	}

	private function insertMouseTrap() {
		$sId = 'V'.rand();
		echo sprintf(
			'<style>#%s{display:block !important;}</style><a href="%s" rel="nofollow" id="%s">%s</a>',
			$sId, $this->getTrapLink(), $sId, 'Click here to see something fantastic'
		);
	}

	/**
	 * @return string
	 */
	private function getTrapLink() {
		/** @var \ICWP_WPSF_FeatureHandler_Firewall $oFO */
		$oFO = $this->getMod();

		$oWp = Services::WpGeneral();
		if ( $oWp->isPermalinksEnabled() ) {
			$sLink = $oWp->getHomeUrl( sprintf( '/%s-%s/', $oFO->prefix( 'mouse' ), $oFO->getMouseTrapKey() ) );
		}
		else {
			$sLink = add_query_arg( [ $oFO->prefix( 'mouse' ) => $oFO->getMouseTrapKey() ], $oWp->getHomeUrl() );
		}
		return $sLink;
	}
}
