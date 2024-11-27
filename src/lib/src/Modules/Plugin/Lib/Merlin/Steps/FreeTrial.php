<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

class FreeTrial extends Base {

	public const SLUG = 'free_trial';

	public function getName() :string {
		return __( 'Free Trial', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		return [
			'hrefs'   => [
				'free_trial' => 'https://clk.shldscrty.com/freetrialwizard',
				'features'   => 'https://getshieldsecurity.com/features/',
			],
			'imgs'    => [
				'free_trial' => self::con()->svgs->raw( 'shield-fill-plus.svg' ),
			],
			'strings' => [
				'step_title' => 'Try ShieldPRO For Free',
			],
		];
	}

	public function skipStep() :bool {
		return self::con()->isPremiumActive();
	}
}