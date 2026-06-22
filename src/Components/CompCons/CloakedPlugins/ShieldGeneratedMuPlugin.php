<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\{
	GeneratedMuLoaderContent,
	MUHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ShieldGeneratedMuPlugin {

	use PluginControllerConsumer;

	private const OPT_ENABLE_MU = 'enable_mu';

	public function isShieldMuLoaderFinding( CloakedPluginFinding $finding ) :bool {
		return $finding->entry->type === PluginType::MustUse
			   && $finding->entry->file === MUHandler::PLUGIN_FILE_NAME;
	}

	public function isGeneratedShieldMuLoaderFinding( CloakedPluginFinding $finding ) :bool {
		return $this->isShieldMuLoaderFinding( $finding )
			   && \is_readable( $finding->entry->path )
			   && self::con()->opts->optIs( self::OPT_ENABLE_MU, 'Y' )
			   && $this->isGeneratedLoaderFile( $finding->entry->path );
	}

	private function isGeneratedLoaderFile( string $path ) :bool {
		$rootFile = self::con()->getRootFile();
		if ( $rootFile === '' || !\is_readable( $path ) ) {
			return false;
		}

		$content = \file_get_contents( $path );
		if ( !\is_string( $content ) ) {
			return false;
		}

		try {
			$expected = ( new GeneratedMuLoaderContent() )->build();
		}
		catch ( \Throwable $e ) {
			return false;
		}

		return \hash_equals(
			$this->normalizeLineEndings( $expected ),
			$this->normalizeLineEndings( $content )
		);
	}

	private function normalizeLineEndings( string $content ) :string {
		return \str_replace( [ "\r\n", "\r" ], "\n", $content );
	}
}
