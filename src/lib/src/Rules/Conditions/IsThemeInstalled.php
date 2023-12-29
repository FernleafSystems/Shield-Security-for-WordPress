<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $file
 */
class IsThemeInstalled extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_theme_installed';

	protected function execConditionCheck() :bool {
		return Services::WpThemes()->isInstalled( $this->file );
	}

	public function getDescription() :string {
		return __( 'Is a given theme installed & active.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'file' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Theme directory name to check', 'wp-simple-firewall' ),
			],
		];
	}
}