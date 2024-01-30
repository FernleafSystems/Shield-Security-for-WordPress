<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BuildRuleCoreShieldBase extends BuildRuleBase {

	use PluginControllerConsumer;

	public const SLUG = '';

	protected function getFlags() :array {
		return [
			'is_core_shield' => true
		];
	}

	protected function getSlug() :string {
		return static::SLUG;
	}

	protected function getCommonAuditParamsMapping() :array {
		return [
			'crawler' => 'matched_useragent',
		];
	}
}