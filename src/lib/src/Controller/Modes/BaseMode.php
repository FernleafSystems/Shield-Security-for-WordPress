<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Modes;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseMode {

	use Shield\Modules\PluginControllerConsumer;

	public const SLUG = '';

	public function enableViaFile() :bool {
		$FS = Services::WpFs();
		if ( !$this->isActiveViaModeFile() ) {
			$FS->touch( $this->getPathToModeFile() );
		}
		return $FS->isAccessibleFile( $this->getPathToModeFile() );
	}

	public function disableViaFile() :bool {
		$FS = Services::WpFs();
		if ( $this->isActiveViaModeFile() ) {
			$FS->deleteFile( $this->getPathToModeFile() );
		}
		return !$FS->isAccessibleFile( $this->getPathToModeFile() );
	}

	public function isModeActive() :bool {
		return $this->isActiveViaDefine() || $this->isActiveViaModeFile();
	}

	public function isActiveViaDefine() :bool {
		$constant = \strtoupper(
			self::con()->prefix( sprintf( 'MODE_%s', static::SLUG ), '_' )
		);
		return \defined( $constant ) && $constant;
	}

	public function isActiveViaModeFile() :bool {
		$FS = Services::WpFs();
		$correctPath = $this->getPathToModeFile();
		$baseFile = \basename( $correctPath );

		// We first look for the presence of the file (which may not be named in all lower-case)
		$foundFile = $FS->findFileInDir( $baseFile, self::con()->paths->forFlag(), false );
		if ( !empty( $foundFile )
			 && $FS->isAccessibleFile( $foundFile ) && !$FS->isAccessibleFile( $correctPath )
			 && $baseFile !== \basename( $foundFile ) ) {
			$FS->move( $foundFile, $correctPath );
		}
		return $FS->isAccessibleFile( $correctPath );
	}

	protected function getPathToModeFile() :string {
		return self::con()->paths->forFlag( sprintf( 'mode.%s', \strtolower( static::SLUG ) ) );
	}
}