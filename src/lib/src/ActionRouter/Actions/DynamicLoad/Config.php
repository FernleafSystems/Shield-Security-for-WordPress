<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad;

/**
 * @deprecated 19.2
 */
class Config extends Base {

	public const SLUG = 'dynamic_load_config';

	protected function getPageUrl() :string {
		return '';
	}

	protected function getPageTitle() :string {
		return '';
	}

	protected function getContent() :string {
		return '';
	}
}