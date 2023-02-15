<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseFullPageRender extends BaseRender {

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		$data = parent::getAllRenderDataArrays();
		$data[ 25 ] = $this->getCommonFullPageRenderData();
		return $data;
	}

	protected function getCommonFullPageRenderData() :array {
		$con = $this->getCon();
		$WP = Services::WpGeneral();
		return [
			'flags'   => [
				'is_whitelabelled' => $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled()
			],
			'head'    => [
				'scripts' => $this->getScripts(),
				'styles'  => $this->getStyles(),
			],
			'hrefs'   => [
				'shield_logo'    => 'https://ps.w.org/wp-simple-firewall/assets/banner-772x250.png',
				'how_to_unblock' => 'https://shsec.io/shieldhowtounblock',
				'helpdesk'       => 'https://shsec.io/shieldhelpdesk'
			],
			'imgs'    => [
				'about_shield' => $con->urls->forImage( 'pluginlogo_128x128.png' ),
				'logo_banner'  => $con->labels->url_img_pagebanner,
				'favicon'      => $con->urls->forImage( 'pluginlogo_24x24.png' ),
				'svgs'         => [
					'megaphone'       => $con->svgs->raw( 'megaphone.svg' ),
					'question_square' => $con->svgs->raw( 'question-square.svg' ),
				],
			],
			'strings' => [
			],
			'vars'    => [
				'visitor_ip' => $con->this_req->ip,
				'time_now'   => $WP->getTimeStringForDisplay(),
				'home_url'   => $WP->getHomeUrl(),
			],
		];
	}

	protected function getScripts() :array {
		$urlBuilder = $this->getCon()->urls;
		return [
			10 => [
				'src' => Services::Includes()->getUrl_Jquery(),
				'id'  => 'wp_jquery',
			],
			20 => [
				'src' => $urlBuilder->forJs( 'bootstrap' ),
				'id'  => 'bootstrap',
			],
			30 => [
				'src' => $urlBuilder->forJs( 'global-plugin' ),
				'id'  => 'shield-global-plugin',
			],
		];
	}

	protected function getStyles() :array {
		$urlBuilder = $this->getCon()->urls;
		return [
			20 => [
				'href' => $urlBuilder->forCss( 'bootstrap' ),
				'id'   => 'bootstrap',
			],
			30 => [
				'href' => $urlBuilder->forCss( 'global-plugin' ),
				'id'   => 'shield-global-plugin',
			],
		];
	}
}