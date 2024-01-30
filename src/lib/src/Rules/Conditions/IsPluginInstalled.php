<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

class IsPluginInstalled extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_plugin_installed';

	protected function execConditionCheck() :bool {
		return Services::WpPlugins()->isInstalled( $this->p->plugin_file );
	}

	public function getDescription() :string {
		return __( 'Is a given plugin installed.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'plugin_file' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Plugin basename to check (e.g. "dir/plugin.php")', 'wp-simple-firewall' ),
			],
		];
	}
}