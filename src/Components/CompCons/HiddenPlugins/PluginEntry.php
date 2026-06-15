<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

class PluginEntry {

	/**
	 * @phpstan-var value-of<PluginType::ALL>
	 */
	public string $type;

	public string $file;

	public string $name;

	public string $version;

	public string $path;

	/**
	 * @phpstan-param value-of<PluginType::ALL> $type
	 */
	public function __construct(
		string $type,
		string $file,
		string $name,
		string $version,
		string $path
	) {
		PluginType::assertValid( $type );

		$this->type = $type;
		$this->file = $file;
		$this->name = $name;
		$this->version = $version;
		$this->path = $path;
	}
}
