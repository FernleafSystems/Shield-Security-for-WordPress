<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render\BasePageDisplay;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseBlockPage extends BasePageDisplay {

	use ModConsumer;

	protected function getResponseCode() :int {
		return 503;
	}

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getMod()->getUIHandler()->getBaseDisplayData(),
			parent::getRenderData(),
			[
				'strings' => [
					'restriction_details'       => __( 'Restriction Details', 'wp-simple-firewall' ),
					'restriction_details_blurb' => $this->getRestrictionDetailsBlurb(),
				],
				'vars'    => [
					'restriction_details_points' => $this->getRestrictionDetailsPoints(),
				],
			],
			$this->getPageSpecificData()
		);
	}

	protected function getPageSpecificData() :array {
		return [];
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
			__( 'Your IP Address', 'wp-simple-firewall' ) => $this->getCon()->this_req->ip,
			__( 'Time Now', 'wp-simple-firewall' )        => $WP->getTimeStringForDisplay(),
			__( 'Homepage', 'wp-simple-firewall' )        => $WP->getHomeUrl(),
		];
	}

	protected function getTemplateBaseDir() :string {
		return '/pages/block/';
	}
}