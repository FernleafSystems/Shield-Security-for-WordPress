<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

abstract class BuildRuleCoreShieldBase extends BuildRuleBase {

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