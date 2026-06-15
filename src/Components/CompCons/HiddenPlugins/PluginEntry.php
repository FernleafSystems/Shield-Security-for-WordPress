<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

readonly class PluginEntry {

	public function __construct(
		public PluginType $type,
		public string $file,
		public string $name,
		public string $version,
		public string $path
	) {
	}
}
