<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class RootHtaccess {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	protected function canRun() :bool {
		return empty( Transient::Get( self::con()->prefix( \hash( 'md5', __FILE__ ) ) ) );
	}

	protected function run() {
		$this->setupCronHooks();
	}

	public function runDailyCron() {
		Transient::Set( self::con()->prefix( \hash( 'md5', __FILE__ ) ), 1, DAY_IN_SECONDS );

		$hadFile = (bool)Services::WpFs()->exists( $this->getPathToHtaccess() );
		$couldAccess = $this->testCanAccessURL();

		if ( $hadFile && !$couldAccess ) {
			$this->deleteHtaccess();
		}
		elseif ( !$hadFile && $couldAccess ) {
			// Create the file and test you can access it. If not, delete it again.
			if ( $this->createHtaccess() && !$this->testCanAccessURL() ) {
				$this->deleteHtaccess();
			}
		}
	}

	private function testCanAccessURL() :bool {
		$httpReq = Services::HttpRequest();
		return $httpReq->get( $this->getTestURL() ) && $httpReq->lastResponse->getCode() < 400;
	}

	private function getTestURL() :string {
		return URL::Build( self::con()->urls->forDistJS( 'main' ), [ 'rand' => \rand( 1000, 9999 ) ] );
	}

	private function getPathToHtaccess() :string {
		return path_join( self::con()->getRootDir(), '.htaccess' );
	}

	private function deleteHtaccess() {
		Services::WpFs()->deleteFile( $this->getPathToHtaccess() );
	}

	private function createHtaccess() :bool {
		return Services::WpFs()->putFileContent(
			$this->getPathToHtaccess(),
			\implode( "\n", [
				'Order Allow,Deny',
				'<FilesMatch "^.*\.(css|js|png|jpg|svg)$" >',
				' Allow from all',
				'</FilesMatch>',
			] )
		);
	}
}

