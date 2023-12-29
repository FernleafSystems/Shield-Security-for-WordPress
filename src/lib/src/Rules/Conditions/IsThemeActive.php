<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $file
 */
class IsThemeActive extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_theme_active';

	protected function execConditionCheck() :bool {
		return Services::WpThemes()->isActive( $this->file );
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