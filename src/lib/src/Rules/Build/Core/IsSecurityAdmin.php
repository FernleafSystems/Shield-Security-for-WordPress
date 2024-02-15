<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
};

class IsSecurityAdmin extends BuildRuleCoreShieldBase {

	use ModConsumer;

	public const SLUG = 'shield/is_security_admin';

	protected function getName() :string {
		return 'Is Security Admin';
	}

	protected function getDescription() :string {
		return 'Is Security Admin.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\IsRequestSecurityAdmin::class,
		];
	}
}