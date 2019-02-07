<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MouseTrap {

	use ModConsumer;

	public function run() {
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
		$sRobotsText .= sprintf( "\nDisallow: %s", parse_url( $this->getTrapLink(), PHP_URL_PATH ) );
		return $sRobotsText;
	}

	private function checkForVenusRequest() {
		if ( $this->isVenusRequest() ) {
			/** @var \ICWP_WPSF_FeatureHandler_Firewall $oFO */
			$oFO = $this->getMod();

			$bBlock = $oFO->isVenusResponseBlock();
			$sAuditMessage = _wpsf__( 'MouseTrap found visitor to be a bot.' );

			if ( $bBlock ) {
				$sAuditMessage .= ' '._wpsf__( 'IP set to be blocked.' );
			}
			else {
				$sAuditMessage .= ' '._wpsf__( 'Transgression counter set to increase.' );
			}

			$oFO->addToAuditEntry( $sAuditMessage, 2, 'venus_botfly_trapped' );
			$bBlock ? $oFO->setIpBlocked() : $this->setIpTransgressed();
			Services::WpGeneral()->wpDie( 'Did you really mean to come here?' );
		}
	}

	protected function isVenusRequest() {
		/** @var \ICWP_WPSF_FeatureHandler_Firewall $oFO */
		$oFO = $this->getMod();

		$oReq = Services::Request();
		if ( Services::WpGeneral()->isPermalinksEnabled() ) {
			$bVenus = trim( $oReq->getPath(), '/' ) == sprintf( '%s-%s', $oFO->prefix( 'mouse' ), $oFO->getVenusKey() );
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
			$sLink = $oWp->getHomeUrl( sprintf( '/%s-%s/', $oFO->prefix( 'mouse' ), $oFO->getVenusKey() ) );
		}
		else {
			$sLink = add_query_arg( array( $oFO->prefix( 'mouse' ) => $oFO->getVenusKey() ), $oWp->getHomeUrl() );
		}
		return $sLink;
	}
}
