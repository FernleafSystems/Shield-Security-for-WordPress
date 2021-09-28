<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\FormatBytes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Mal,
	Ptg,
	Ufc,
	Wcf
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class LoadRawTableData {

	use ModConsumer;

	private static $GuardFiles;

	/**
	 * @param string $type
	 * @param string $file
	 * @return array
	 * @throws \Exception
	 */
	public function loadFor( string $type, string $file ) :array {

		switch ( $type ) {

			case 'plugin':
				$item = Services::WpPlugins()->getPluginAsVo( $file );
				if ( empty( $item ) ) {
					throw new \Exception( '[LoadRawTableData] Unsupported slug: '.$file );
				}
				$data = $this->loadForPlugin( $item );
				break;

			case 'theme':
				$item = Services::WpThemes()->getThemeAsVo( $file );
				if ( empty( $item ) ) {
					throw new \Exception( '[LoadRawTableData] Unsupported slug: '.$file );
				}
				$data = $this->loadForTheme( $item );
				break;

			case 'wordpress':
				$data = $this->loadForWordPress();
				break;

			case 'malware':
				$data = $this->loadForMalware();
				break;

			default:
				throw new \Exception( '[LoadRawTableData] Unsupported type: '.$type );
		}

		return $data;
	}

	public function loadForMalware() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return array_map(
			function ( $item ) {
				/** @var Scans\Mal\ResultItem $item */
				$data = $item->getRawData();

				$data[ 'rid' ] = $item->VO->scanresult_id;
				$data[ 'file' ] = $item->path_fragment;
				$data[ 'detected_at' ] = $item->VO->created_at;
				$data[ 'detected_since' ] = Services::Request()
													->carbon( true )
													->setTimestamp( $item->VO->created_at )
													->diffForHumans();

				$data[ 'file_as_href' ] = $this->getColumnContent_File( $item );

				$data[ 'status_slug' ] = 'malware';
				$data[ 'status' ] = $this->getColumnContent_FileStatus( $item, __( 'Malware', 'wp-simple-firewall' ) );

				$data[ 'line_numbers' ] = implode( ', ', array_map(
					function ( $line ) {
						return $line + 1;
					},
					$item->file_lines // because lines start at ZERO
				) );
				$data[ 'mal_sig' ] = sprintf( '<code style="white-space: nowrap">%s</code>', esc_html( base64_decode( $item->mal_sig ) ) );
				$data[ 'file_type' ] = strtoupper( Services::Data()->getExtension( $item->path_full ) );
				$data[ 'actions' ] = implode( ' ', $this->getActions( $data[ 'status_slug' ], $item ) );

				return $data;
			},
			$mod->getScanCon( Mal::SCAN_SLUG )->getAllResults()->getItems()
		);
	}

	public function loadForPlugin( WpPluginVo $plugin ) :array {
		return $this->getGuardFilesDataFor( $plugin );
	}

	public function loadForTheme( WpThemeVo $theme ) :array {
		return $this->getGuardFilesDataFor( $theme );
	}

	public function loadForWordPress() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			$files = array_map(
				function ( $item ) {
					/** @var Scans\Wcf\ResultItem|Scans\Ufc\ResultItem $item */
					$data = $item->getRawData();

					$data[ 'rid' ] = $item->VO->scanresult_id;
					$data[ 'file' ] = $item->path_fragment;
					$data[ 'detected_at' ] = $item->VO->created_at;
					$data[ 'detected_since' ] = Services::Request()
														->carbon( true )
														->setTimestamp( $item->VO->created_at )
														->diffForHumans();

					if ( !$item->is_missing ) {
						$data[ 'file_as_href' ] = $this->getColumnContent_File( $item );
					}
					else {
						$data[ 'file_as_href' ] = $item->path_fragment;
					}

					if ( $item->is_checksumfail ) {
						$data[ 'status_slug' ] = 'modified';
						$data[ 'status' ] = __( 'Modified', 'wp-simple-firewall' );
					}
					elseif ( $item->is_missing ) {
						$data[ 'status_slug' ] = 'missing';
						$data[ 'status' ] = __( 'Missing', 'wp-simple-firewall' );
					}
					else {
						$data[ 'status_slug' ] = 'unrecognised';
						$data[ 'status' ] = __( 'Unrecognised', 'wp-simple-firewall' );
					}
					$data[ 'status' ] = $this->getColumnContent_FileStatus( $item, $data[ 'status' ] );

					$data[ 'file_type' ] = strtoupper( Services::Data()->getExtension( $item->path_full ) );
					$data[ 'actions' ] = implode( ' ', $this->getActions( $data[ 'status_slug' ], $item ) );
					error_log( var_export( $data, true ) );
					return $data;
				},
				array_merge(
					$mod->getScanCon( Wcf::SCAN_SLUG )->getAllResults()->getItems(),
					$mod->getScanCon( Ufc::SCAN_SLUG )->getAllResults()->getItems()
				)
			);
		}
		catch ( \Exception $e ) {
			$files = [];
		}

		return $files;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $item
	 * @return array
	 */
	private function getGuardFilesDataFor( $item ) :array {
		return array_map(
			function ( $item ) {

				$data = $item->getRawData();
				$data[ 'rid' ] = $item->VO->scanresult_id;
				$data[ 'file' ] = $item->path_fragment;
				$data[ 'detected_at' ] = $item->VO->created_at;
				$data[ 'detected_since' ] = Services::Request()
													->carbon( true )
													->setTimestamp( $item->VO->created_at )
													->diffForHumans();

				if ( $item->is_different ) {
					$data[ 'status_slug' ] = 'modified';
					$data[ 'status' ] = __( 'Modified', 'wp-simple-firewall' );
				}
				elseif ( $item->is_missing ) {
					$data[ 'status_slug' ] = 'missing';
					$data[ 'status' ] = __( 'Missing', 'wp-simple-firewall' );
				}
				else {
					$data[ 'status_slug' ] = 'unrecognised';
					$data[ 'status' ] = __( 'Unrecognised', 'wp-simple-firewall' );
				}
				$data[ 'status' ] = $this->getColumnContent_FileStatus( $item, $data[ 'status' ] );

				if ( !$item->is_missing ) {
					$data[ 'file_as_href' ] = $this->getColumnContent_File( $item );
				}
				else {
					$data[ 'file_as_href' ] = $item->path_fragment;
				}

				$data[ 'file_type' ] = strtoupper( Services::Data()->getExtension( $item->path_full ) );
				$data[ 'actions' ] = implode( ' ', $this->getActions( $data[ 'status_slug' ], $item ) );
				return $data;
			},
			$this->getGuardFiles()->getItemsForSlug( $item->asset_type === 'plugin' ? $item->file : $item->stylesheet )
		);
	}

	/**
	 * @param string                $status
	 * @param Scans\Base\ResultItem $item
	 * @return array
	 * @throws \Exception
	 */
	private function getActions( string $status, $item ) :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$actionHandler = $mod->getScanCon( $item->scan )
							 ->getItemActionHandler()
							 ->setScanItem( $item );

		$actions = [];

		$defaultButtonClasses = [
			'btn',
			'action',
		];

		if ( in_array( $status, [ 'unrecognised', 'malware' ] ) ) {
			$actions[] = sprintf( '<button class="btn-danger delete %s" title="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'Delete', 'wp-simple-firewall' ),
				$item->VO->scanresult_id,
				$con->svgs->raw( 'bootstrap/x-square.svg' )
			);
		}

		try {
			if ( in_array( $status, [ 'modified', 'missing', 'malware' ] ) && $actionHandler->getRepairer()
																							->canRepair() ) {
				$actions[] = sprintf( '<button class="btn-warning repair %s" title="%s" data-rid="%s">%s</button>',
					implode( ' ', $defaultButtonClasses ),
					__( 'Repair', 'wp-simple-firewall' ),
					$item->VO->scanresult_id,
					$con->svgs->raw( 'bootstrap/tools.svg' )
				);
			}
		}
		catch ( \Exception $e ) {
		}

		if ( in_array( $status, [ 'modified', 'unrecognised', 'malware' ] ) ) {
			$actions[] = sprintf( '<button class="btn-dark href-download %s" title="%s" data-href-download="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'Download', 'wp-simple-firewall' ),
				$mod->getScanCon( $item->scan )->createFileDownloadLink( $item->VO->scanresult_id ),
				$con->svgs->raw( 'bootstrap/download.svg' )
			);
		}

		$actions[] = sprintf( '<button class="btn-light ignore %s" title="%s" data-rid="%s">%s</button>',
			implode( ' ', $defaultButtonClasses ),
			__( 'Ignore', 'wp-simple-firewall' ),
			$item->VO->scanresult_id,
			$con->svgs->raw( 'bootstrap/eye-slash-fill.svg' )
		);

		return $actions;
	}

	private function getGuardFiles() :Scans\Ptg\ResultsSet {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( !isset( self::$GuardFiles ) ) {
			try {
				self::$GuardFiles = $mod->getScanCon( Ptg::SCAN_SLUG )->getAllResults();
			}
			catch ( \Exception $e ) {
				self::$GuardFiles = new Scans\Ptg\ResultsSet();
			}
		}
		return self::$GuardFiles;
	}

	private function getColumnContent_File( Scans\Base\FileResultItem $item ) :string {
		return sprintf( '<div>%s</div>', $this->getColumnContent_FileAsHref( $item ) );
	}

	private function getColumnContent_FileStatus( Scans\Base\FileResultItem $item, string $status ) :string {
		$content = $status;

		$FS = Services::WpFs();
		if ( $FS->isFile( $item->path_full ) ) {
			$carbon = Services::Request()->carbon( true );
			$content = sprintf( '%s<ul style="list-style: square inside"><li>%s</li></ul>',
				$status,
				implode( '</li><li>', [
					sprintf( '%s: %s', __( 'Modified', 'wp-simple-firewall' ),
						$carbon->setTimestamp( $FS->getModifiedTime( $item->path_full ) )
							   ->diffForHumans()
					),
					sprintf( '%s: %s', __( 'Size', 'wp-simple-firewall' ),
						FormatBytes::Format( $FS->getFileSize( $item->path_full ) )
					)
				] )
			);
		}

		return $content;
	}

	private function getColumnContent_FileAsHref( Scans\Base\FileResultItem $item ) :string {
		return sprintf( '<a href="#" title="%s" class="action view-file" data-rid="%s">%s</a>',
			__( 'View File Contents', 'wp-simple-firewall' ),
			$item->VO->scanresult_id,
			$item->path_fragment
		);
	}
}