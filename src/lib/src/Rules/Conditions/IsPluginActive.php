<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $file
 */
class IsPluginActive extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_plugin_active';

	protected function execConditionCheck() :bool {
		return Services::WpPlugins()->isActive( $this->file );
	}

	public function getDescription() :string {
		return __( 'Is a given plugin installed & active.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'file' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Plugin basename to check (e.g. "dir/plugin.php")', 'wp-simple-firewall' ),
			],
		];
	}
}