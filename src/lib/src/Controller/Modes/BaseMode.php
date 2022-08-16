<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Modes;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseMode {

	use Shield\Modules\PluginControllerConsumer;

	const SLUG = '';

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

	public function isModeActive() :bool {
		return $this->isActiveViaDefine() || $this->isActiveViaModeFile();
	}

	public function isActiveViaDefine() :bool {
		$constant = strtoupper(
			$this->getCon()->prefix( sprintf( 'MODE_%s', static::SLUG ), '_' )
		);
		return defined( $constant ) && $constant;
	}

	public function isActiveViaModeFile() :bool {
		$con = $this->getCon();
		$FS = Services::WpFs();
		$correctPath = $this->getPathToModeFile();
		$baseFile = basename( $correctPath );

		// We first look for the presence of the file (which may not be named in all lower-case)
		$foundFile = $FS->findFileInDir( $baseFile, $con->paths->forFlag(), false );
		if ( !empty( $foundFile )
			 && $FS->isFile( $foundFile ) && !$FS->isFile( $correctPath )
			 && basename( $correctPath ) !== basename( $foundFile ) ) {
			$FS->move( $foundFile, $correctPath );
		}
		return $FS->isFile( $correctPath );
	}

	protected function getPathToModeFile() :string {
		return $this->getCon()->paths->forFlag( sprintf( 'mode.%s', strtolower( static::SLUG ) ) );
	}
}