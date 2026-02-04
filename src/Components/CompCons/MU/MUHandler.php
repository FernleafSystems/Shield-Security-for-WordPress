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
				throw new \Exception( sprintf( __( 'Could not delete the MU file: %s', 'wp-simple-firewall' ), $file ) );
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
			throw new \Exception( sprintf( __( 'WordPress version must be at least: %s', 'wp-simple-firewall' ), '5.6' ) );
		}

		$muDir = $this->getMuDir();
		if ( !$FS->isDir( $muDir ) ) {
			$FS->mkdir( $muDir );
		}

		if ( !$FS->isDir( $muDir ) ) {
			throw new \Exception( sprintf( __( 'Could not create MU directory: %s', 'wp-simple-firewall' ), $muDir ) );
		}

		$file = $this->getMuFilePath();
		$content = $this->buildContent();
		$FS->putFileContent( $file, $content );

		if ( !$FS->isAccessibleFile( $file ) ) {
			throw new \Exception( sprintf( __( 'Could not create MU file: %s', 'wp-simple-firewall' ), $file ) );
		}
		if ( $FS->getFileContent( $file ) !== $content ) {
			throw new \Exception( sprintf( __( 'Could not write content to MU file: %s', 'wp-simple-firewall' ), $file ) );
		}

		// Now test we haven't destroyed the site loading.
		if ( !$this->testLoopback() ) {
			$this->convertToStandard();
			throw new \Exception( __( 'Cancelled - could not verify site loads successfully', 'wp-simple-firewall' ) );
		}

		return $this->isActiveMU();
	}

	protected function testLoopback() :bool {
		return ( Services::Rest()->callInternal( [
				'route' => '/wp-site-health/v1/tests/loopback-requests'
			] )->get_data()[ 'status' ] ?? '' ) === 'good';
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
		$templateFile = path_join( __DIR__, '.mu-template.txt' );
		$template = Services::WpFs()->getFileContent( $templateFile );
		if ( empty( $template ) ) {
			throw new \Exception( sprintf( __( "Couldn't read MU plugin template from %s", 'wp-simple-firewall' ), $templateFile ) );
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
