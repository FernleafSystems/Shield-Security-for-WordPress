<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render;

use FernleafSystems\Wordpress\Services\Services;

abstract class BasePageDisplay extends BaseTemplateRenderer {
	public function display() {
		$this->issueHeaders();
		echo $this->render();
		$this->complete();
	}

	protected function issueHeaders() {
		http_response_code( $this->getResponseCode() );
		nocache_headers();
		if ( $this->isCacheDisabled() ) {
			Services::WpGeneral()->turnOffCache();
		}
	}

	protected function complete() {
		die();
	}

	protected function getRenderData() :array {
		$con = $this->getCon();
		$WP = Services::WpGeneral();
		$labels = $con->getLabels();
		$bannerURL = empty( $labels[ 'url_login2fa_logourl' ] ) ? $con->urls->forImage( 'shield/banner-2FA.png' ) : $labels[ 'url_login2fa_logourl' ];

		return [
			'flags'   => [
				'is_whitelabelled' => $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled()
			],
			'head'    => [
			],
			'hrefs'   => [
				'css_bootstrap'  => $con->urls->forCss( 'bootstrap' ),
				'js_bootstrap'   => $con->urls->forJs( 'bootstrap' ),
				'shield_logo'    => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'how_to_unblock' => 'https://shsec.io/shieldhowtounblock',
			],
			'imgs'    => [
				'about_shield' => $con->urls->forImage( 'pluginlogo_128x128.png' ),
				'banner'       => $bannerURL,
				'favicon'      => $con->urls->forImage( 'pluginlogo_24x24.png' ),
			],
			'strings' => [
			],
			'vars'    => [
				'visitor_ip' => Services::IP()->getRequestIp(),
				'time_now'   => $WP->getTimeStringForDisplay(),
				'home_url'   => $WP->getHomeUrl(),
			],
		];
	}

	protected function getResponseCode() :int {
		return 200;
	}

	protected function isCacheDisabled() :bool {
		return true;
	}
}