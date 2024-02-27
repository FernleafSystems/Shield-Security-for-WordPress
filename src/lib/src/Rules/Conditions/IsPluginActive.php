<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumLogic,
	EnumParameters
};

class IsPluginActive extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_plugin_active';

	protected function execConditionCheck() :bool {
		return is_plugin_active( $this->p->plugin_file );
	}

	public function getDescription() :string {
		return __( 'Is a given plugin installed & active.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'plugin_file' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Plugin basename to check (e.g. "dir/plugin.php")', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsPluginInstalled::class,
					'params'     => [
						'plugin_file' => $this->p->plugin_file,
					],
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}