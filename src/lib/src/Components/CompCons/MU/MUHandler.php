<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MUHandler {

	use PluginControllerConsumer;

	public const PLUGIN_FILE_NAME = 'a-shield-mu.php';

	public function run() {
		try {
			self::con()->opts->optIs( 'enable_mu', 'Y' ) ? $this->convertToMU() : $this->convertToStandard();
		}
		catch ( \Exception $e ) {
		}
		finally {
			self::con()->opts->optSet( 'enable_mu', $this->isActiveMU() ? 'Y' : 'N' );
		}
	}

	public function isActiveMU() :bool {
		return Services::WpFs()->isAccessibleFile( $this->getMuFilePath() );
	}

	/**
	 * @throws \Exception
	 */
	public function convertToStandard() :bool {
		if ( $this->isActiveMU() ) {
			$file = $this->getMuFilePath();
			Services::WpFs()->deleteFile( $file );
			if ( $this->isActiveMU() ) {
				throw new \Exception( sprintf( 'Could not delete the MU File: %s', $file ) );
			}
		}
		return !$this->isActiveMU();
	}

	/**
	 * @throws \Exception
	 */
	public function convertToMU() :bool {
		$FS = Services::WpFs();

		if ( !Services::WpGeneral()->getWordpressIsAtLeastVersion( '5.6' ) ) {
			throw new \Exception( sprintf( 'WP Version must be at least: %s', '5.6' ) );
		}

		$muDir = $this->getMuDir();
		if ( !$FS->isDir( $muDir ) ) {
			$FS->mkdir( $muDir );
		}

		if ( !$FS->isDir( $muDir ) ) {
			throw new \Exception( sprintf( 'Could not create MU Dir: %s', $muDir ) );
		}

		$file = $this->getMuFilePath();
		$content = $this->buildContent();
		$FS->putFileContent( $file, $content );

		if ( !$FS->isAccessibleFile( $file ) ) {
			throw new \Exception( sprintf( 'Could not create MU File: %s', $file ) );
		}
		if ( $FS->getFileContent( $file ) !== $content ) {
			throw new \Exception( sprintf( 'Could not write content to MU File: %s', $file ) );
		}

		// Now test we haven't destroyed the site loading.
		if ( !$this->testLoopback() ) {
			$this->convertToStandard();
			throw new \Exception( "Cancelled - Could not verify site loads successfully" );
		}

		return $this->isActiveMU();
	}

	protected function testLoopback() :bool {
		$status = Services::Rest()->callInternal( [
			'route' => '/wp-site-health/v1/tests/loopback-requests'
		] )->get_data()[ 'status' ] ?? '';
		return $status === 'good';
	}

	private function getMuFilePath() :string {
		return path_join( $this->getMuDir(), self::PLUGIN_FILE_NAME );
	}

	private function getMuDir() :string {
		return \defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR :
			path_join( \dirname( self::con()->getRootDir(), 2 ), 'mu-plugins' );
	}

	/**
	 * @throws \Exception
	 */
	private function buildContent() :string {
		$con = self::con();
		$FS = Services::WpFs();
		$templateFile = path_join( __DIR__, '.mu-template.txt' );
		$template = $FS->getFileContent( $templateFile );
		if ( empty( $template ) ) {
			throw new \Exception( sprintf( "Couldn't read mu-plugin template from %s", $templateFile ) );
		}
		$replacements = [
			'SHIELD_ROOT_FILE'     => $con->getRootFile(),
			'SHIELD_PLUGIN_NAME'   => $con->labels->Name,
			'SHIELD_PLUGIN_URL'    => $con->labels->PluginURI,
			'SHIELD_PLUGIN_AUTHOR' => $con->labels->Author,
		];
		return \str_replace( \array_keys( $replacements ), \array_values( $replacements ), $template );
	}
}