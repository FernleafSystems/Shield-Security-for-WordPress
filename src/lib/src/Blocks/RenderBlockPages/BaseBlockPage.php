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

	protected function getData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getMod()->getUIHandler()->getBaseDisplayData(),
			parent::getData(),
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
			__( "This website uses a security service to monitor requests, checking for activity that isn't normal or expected.", 'wp-simple-firewall' ),
		];
	}

	protected function getRestrictionDetailsPoints() :array {
		$WP = Services::WpGeneral();
		return [
			__( 'Your IP Address', 'wp-simple-firewall' ) => Services::IP()->getRequestIp(),
			__( 'Time Now', 'wp-simple-firewall' )        => $WP->getTimeStringForDisplay(),
			__( 'Homepage', 'wp-simple-firewall' )        => $WP->getHomeUrl(),
		];
	}

	protected function getTemplateBaseDir() :string {
		return '/pages/block/';
	}
}