<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\BaseFullPageRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseBlock extends BaseFullPageRender {

	use AuthNotRequired;

	public const TEMPLATE = '/pages/block/block_page_standard.twig';

	protected function getCommonFullPageRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getCommonFullPageRenderData(),
			[
				'strings' => [
					'restriction_details'       => __( 'Restriction Details', 'wp-simple-firewall' ),
					'restriction_details_blurb' => $this->getRestrictionDetailsBlurb(),
				],
				'vars'    => [
					'restriction_details_points' => $this->getRestrictionDetailsPoints(),
				],
			]
		);
	}

	protected function getRestrictionDetailsBlurb() :array {
		return [
			'this_website'      => __( "This website uses a security service to monitor requests to check for activity that is malicious, abnormal or unexpected.", 'wp-simple-firewall' ),
			'activity_recorded' => __( "This activity will have been recorded against your IP address and you may be completely blocked from further site access if similar activity is repeated.", 'wp-simple-firewall' ),
		];
	}

	protected function getRestrictionDetailsPoints() :array {
		$WP = Services::WpGeneral();
		return [
			__( 'Your IP Address', 'wp-simple-firewall' ) => self::con()->this_req->ip,
			__( 'Time Now', 'wp-simple-firewall' )        => $WP->getTimeStringForDisplay(),
			__( 'Homepage', 'wp-simple-firewall' )        => $WP->getHomeUrl(),
		];
	}

	protected function getScripts() :array {
		$scripts = parent::getScripts();
		$scripts[ 51 ] = [
			'src'    => self::con()->urls->forDistJS( 'blockpage' ),
			'id'     => 'shield/blockpage',
			'footer' => true,
		];
		return $scripts;
	}

	protected function getStyles() :array {
		$scripts = parent::getStyles();
		$scripts[ 51 ] = [
			'href' => self::con()->urls->forDistCSS( 'blockpage' ),
			'id'   => 'shield/blockpage',
		];
		return $scripts;
	}
}