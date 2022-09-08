<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ThankYou extends Base {

	const SLUG = 'thank_you';

	public function getName() :string {
		return 'Thank You';
	}

	protected function getStepRenderData() :array {
		$con = $this->getCon();
		$user = Services::WpUsers()->getCurrentWpUser();
		return [
			'hrefs'   => [
				'facebook' => 'https://shsec.io/pluginshieldsecuritygroupfb',
				'twitter'  => 'https://shsec.io/pluginshieldsecuritytwitter',
				'email'    => 'https://shsec.io/pluginshieldsecuritynewsletter',
			],
			'imgs'    => [
				'facebook' => $con->svgs->raw( 'bootstrap/facebook.svg' ),
				'twitter'  => $con->svgs->raw( 'bootstrap/twitter.svg' ),
				'email'    => $con->svgs->raw( 'bootstrap/envelope-fill.svg' ),
			],
			'vars'    => [
				'video_id' => '269364269',
			],
			'strings' => [
				'step_title' => 'Thank You For Choosing Shield Security',
			],
		];
	}
}