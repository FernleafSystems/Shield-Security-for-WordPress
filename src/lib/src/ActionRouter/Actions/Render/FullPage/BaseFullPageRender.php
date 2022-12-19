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
		return array_merge( parent::getAllRenderDataArrays(), [
			25 => $this->getCommonPageRenderData()
		] );
	}

	protected function getCommonPageRenderData() :array {
		$con = $this->getCon();
		$WP = Services::WpGeneral();

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
				'helpdesk'       => 'https://shsec.io/shieldhelpdesk'
			],
			'imgs'    => [
				'about_shield' => $con->urls->forImage( 'pluginlogo_128x128.png' ),
				'logo_banner'  => $con->labels->url_img_pagebanner,
				'favicon'      => $con->urls->forImage( 'pluginlogo_24x24.png' ),
				'svgs'         => [
					'megaphone'       => $con->svgs->raw( 'bootstrap/megaphone.svg' ),
					'question_square' => $con->svgs->raw( 'bootstrap/question-square.svg' ),
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
}