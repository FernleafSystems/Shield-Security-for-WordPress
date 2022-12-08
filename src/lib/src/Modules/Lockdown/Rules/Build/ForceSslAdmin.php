<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Responses
};

class ForceSslAdmin extends BuildRuleCoreShieldBase {

	public const SLUG = 'shield/force_ssl_admin';

	protected function getName() :string {
		return 'Force SSL Admin';
	}

	protected function getDescription() :string {
		return 'Force SSL Admin.';
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\ForceSslAdmin::SLUG,
			],
		];
	}
}