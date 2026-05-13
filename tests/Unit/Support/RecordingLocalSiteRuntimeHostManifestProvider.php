<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeHostManifestProvider;

class RecordingLocalSiteRuntimeHostManifestProvider extends LocalSiteRuntimeHostManifestProvider {

	/** @var array<int,array{root_dir:string,mode:string,has_output_callback:bool}> */
	public array $calls = [];

	/**
	 * @var array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>}
	 */
	private array $manifest;

	/**
	 * @param array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>}|null $manifest
	 */
	public function __construct( ?array $manifest = null ) {
		$this->manifest = $manifest ?? [
			'schema_version' => self::STATE_SCHEMA_VERSION,
			'generated_at_unix' => 1,
			'files' => [
				'icwp-wpsf.php' => [
					'sha256' => \str_repeat( 'a', 64 ),
					'size' => 1,
				],
			],
		];
	}

	public function manifest( string $rootDir, string $mode = self::MODE_FULL, ?callable $onOutput = null ) :array {
		$this->calls[] = [
			'root_dir' => $rootDir,
			'mode' => $mode,
			'has_output_callback' => $onOutput !== null,
		];

		return $this->manifest;
	}
}
