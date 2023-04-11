<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Services\Services;

class OptIn extends Base {

	public const SLUG = 'opt_in';

	public function getName() :string {
		return __( 'Join Us!', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$user = Services::WpUsers()->getCurrentWpUser();
		return [
			'hrefs'   => [
				'facebook' => 'https://shsec.io/pluginshieldsecuritygroupfb',
				'twitter'  => 'https://shsec.io/pluginshieldsecuritytwitter',
				'email'    => 'https://shsec.io/pluginshieldsecuritynewsletter',
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