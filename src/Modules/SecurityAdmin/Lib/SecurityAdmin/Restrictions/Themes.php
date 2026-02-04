<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Restrictions;

class Themes extends BaseCapabilitiesRestrict {

	public const AREA_SLUG = 'themes';

	protected function getApplicableCapabilities() :array {
		return [
			'switch_themes',
			'edit_theme_options',
			'install_themes',
			'update_themes',
			'delete_themes'
		];
	}
}