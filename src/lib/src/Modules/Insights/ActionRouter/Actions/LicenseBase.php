<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

abstract class LicenseBase extends BaseAction {

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'license',
		];
	}
}