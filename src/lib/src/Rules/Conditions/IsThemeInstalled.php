<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

class IsThemeInstalled extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_theme_installed';

	protected function execConditionCheck() :bool {
		return Services::WpThemes()->isInstalled( $this->p->theme_dir );
	}

	public function getDescription() :string {
		return __( 'Is a given theme installed.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'theme_dir' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Theme directory name to check', 'wp-simple-firewall' ),
			],
		];
	}
}