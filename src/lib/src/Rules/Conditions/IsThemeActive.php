<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumLogic,
	EnumParameters
};
use FernleafSystems\Wordpress\Services\Services;

class IsThemeActive extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_theme_active';

	protected function execConditionCheck() :bool {
		return Services::WpThemes()->isActive( $this->p->theme_dir );
	}

	public function getDescription() :string {
		return __( 'Is a given theme installed & active.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsThemeInstalled::class,
					'params'     => [
						'plugin_file' => $this->p->theme_dir,
					],
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
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