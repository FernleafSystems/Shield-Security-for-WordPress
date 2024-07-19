<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions\{
	MalwareFileException,
	PluginFileChecksumFailException,
	PluginFileUnrecognisedException,
	RealtimeFileDiscoveredException,
	ThemeFileChecksumFailException,
	ThemeFileUnrecognisedException,
	WpContentFileUnidentifiedException,
	WpCoreFileChecksumFailException,
	WpCoreFileMissingException,
	WpCoreFileUnrecognisedException,
	WpRootFileUnidentifiedException
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\IsFilePathExcluded;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanActionConsumer;

abstract class BaseScan {

	use ScanActionConsumer;
	use PluginControllerConsumer;

	/**
	 * @var string
	 */
	protected $pathFragment;

	/**
	 * @var string
	 */
	protected $pathFull;

	public function __construct( string $pathFull ) {
		$this->setPathFull( $pathFull );
	}

	/**
	 * @throws MalwareFileException
	 * @throws PluginFileChecksumFailException
	 * @throws PluginFileUnrecognisedException
	 * @throws RealtimeFileDiscoveredException
	 * @throws ThemeFileChecksumFailException
	 * @throws ThemeFileUnrecognisedException
	 * @throws WpContentFileUnidentifiedException
	 * @throws WpCoreFileChecksumFailException
	 * @throws WpCoreFileMissingException
	 * @throws WpCoreFileUnrecognisedException
	 * @throws WpRootFileUnidentifiedException
	 * @throws \InvalidArgumentException
	 */
	public function isFileValid() :bool {
		return $this->canScan() && $this->runScan();
	}

	/**
	 * @throws MalwareFileException
	 * @throws PluginFileUnrecognisedException
	 * @throws PluginFileChecksumFailException
	 * @throws RealtimeFileDiscoveredException
	 * @throws ThemeFileUnrecognisedException
	 * @throws ThemeFileChecksumFailException
	 * @throws WpContentFileUnidentifiedException
	 * @throws WpCoreFileChecksumFailException
	 * @throws WpCoreFileMissingException
	 * @throws WpCoreFileUnrecognisedException
	 * @throws WpRootFileUnidentifiedException
	 * @throws \InvalidArgumentException
	 */
	abstract protected function runScan() :bool;

	protected function canScan() :bool {
		return $this->isSupportedFileExt() && !$this->isFileExcluded();
	}

	protected function getSupportedFileExtensions() :array {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		return \is_array( $action->file_exts ) ? $action->file_exts : [];
	}

	protected function isFileExcluded() :bool {
		return ( new IsFilePathExcluded() )->check( $this->pathFull, $this->getPathExcludes() );
	}

	protected function getPathExcludes() :array {
		return [
			'error_log',
			'php_error_log',
			'.htaccess',
			'.htpasswd',
			'.user.ini',
			'php.ini',
			'web.config',
			'php_mail.log',
			'mail.log',
			'wp-content/uploads/bb-plugin/cache/',
			'wp-content/uploads/cache/wpml/twig/',
		];
	}

	protected function isSupportedFileExt() :bool {
		$ext = \strtolower( (string)\pathinfo( $this->pathFull, PATHINFO_EXTENSION ) );
		return !empty( $ext ) && \in_array( $ext, $this->getSupportedFileExtensions() );
	}

	public function setPathFull( string $pathFull ) {
		$this->pathFull = $pathFull;
		$this->pathFragment = \str_replace( wp_normalize_path( ABSPATH ), '', $pathFull );
	}
}