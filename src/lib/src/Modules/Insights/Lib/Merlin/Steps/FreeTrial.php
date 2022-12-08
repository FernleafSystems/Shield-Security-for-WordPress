<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

class FreeTrial extends Base {

	public const SLUG = 'free_trial';

	public function getName() :string {
		return 'Free Trial';
	}

	protected function getStepRenderData() :array {
		return [
			'hrefs'   => [
				'free_trial' => 'https://shsec.io/freetrialwizard',
				'features'   => 'https://getshieldsecurity.com/features/',
			],
			'imgs'    => [
				'free_trial' => $this->getCon()->svgs->raw( 'bootstrap/shield-fill-plus.svg' ),
			],
			'strings' => [
				'step_title' => 'Try ShieldPRO For Free',
			],
		];
	}

	public function skipStep() :bool {
		return $this->getCon()->isPremiumActive();
	}
}