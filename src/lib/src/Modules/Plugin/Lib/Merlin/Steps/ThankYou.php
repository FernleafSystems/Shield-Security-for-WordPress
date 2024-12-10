<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

class ThankYou extends Base {

	public const SLUG = 'thank_you';

	public function getName() :string {
		return __( 'Thanks!', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$con = self::con();
		return [
			'hrefs'   => [
				'facebook' => 'https://clk.shldscrty.com/pluginshieldsecuritygroupfb',
				'twitter'  => 'https://clk.shldscrty.com/pluginshieldsecuritytwitter',
				'email'    => 'https://clk.shldscrty.com/pluginshieldsecuritynewsletter',
			],
			'imgs'    => [
				'facebook' => $con->svgs->raw( 'facebook.svg' ),
				'twitter'  => $con->svgs->raw( 'twitter.svg' ),
				'email'    => $con->svgs->raw( 'envelope-fill.svg' ),
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