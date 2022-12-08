<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Services\Services;

class OptIn extends Base {

	public const SLUG = 'opt_in';

	public function getName() :string {
		return 'Join Us!';
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
				'name'  => $user->first_name,
				'email' => $user->user_email
			],
			'strings' => [
				'step_title' => 'Come Join Us!',
			],
		];
	}
}