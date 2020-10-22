<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class DebugMode {

	use Shield\Modules\PluginControllerConsumer;

	public function enableViaFile() :bool {
		$FS = Services::WpFs();
		if ( !$this->isActiveViaModeFile() ) {
			$FS->touch( $this->getPathToModeFile() );
		}
		return $FS->isFile( $this->getPathToModeFile() );
	}

	public function disableViaFile() :bool {
		$FS = Services::WpFs();
		if ( $this->isActiveViaModeFile() ) {
			$FS->deleteFile( $this->getPathToModeFile() );
		}
		return !$FS->isFile( $this->getPathToModeFile() );
	}

	public function isDebugMode() :bool {
		return $this->isActiveViaDefine() || $this->isActiveViaModeFile();
	}

	public function isActiveViaDefine() :bool {
		$constant = strtoupper( $this->getCon()->prefix( 'DEBUG_MODE', '_' ) );
		return defined( $constant ) && $constant;
	}

	public function isActiveViaModeFile() :bool {
		$con = $this->getCon();
		$FS = Services::WpFs();
		$correctPath = $con->getPath_Flags( 'mode.debug' );

		// We first look for the presence of the file (which may not be named in all lower-case)
		$foundFile = $FS->findFileInDir( 'mode.debug', $con->getPath_Flags(), false, false );
		if ( !empty( $foundFile )
			 && $FS->isFile( $foundFile ) && !$FS->isFile( $correctPath )
			 && !basename( $correctPath ) !== basename( $foundFile ) ) {
			$FS->move( $foundFile, $correctPath );
		}
		return $FS->isFile( $correctPath );
	}

	private function getPathToModeFile() :string {
		return $this->getCon()->getPath_Flags( 'mode.debug' );
	}
}