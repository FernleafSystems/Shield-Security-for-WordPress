<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Ptg;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
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

			default:
				throw new \Exception( '[LoadRawTableData] Unsupported type: '.$type );
		}

		return $data;
	}

	public function loadForPlugin( WpPluginVo $plugin ) :array {
		return $this->getGuardFilesDataFor( $plugin );
	}

	public function loadForTheme( WpThemeVo $theme ) :array {
		return $this->getGuardFilesDataFor( $theme );
	}

	/**
	 * @param WpPluginVo|WpThemeVo $item
	 * @return array
	 */
	private function getGuardFilesDataFor( $item ) :array {
		return array_map(
			function ( $item ) {
				$data = $item->getRawData();
				$data[ 'rid' ] = $item->record_id;
				$data[ 'file' ] = $item->path_fragment;
				$data[ 'status' ] = $item->is_different ? 'modified' : ( $item->is_missing ? 'missing' : 'unrecognised' );
				$data[ 'file_type' ] = strtoupper( Services::Data()->getExtension( $item->path_full ) );
				$data[ 'actions' ] = implode( ' ', $this->getActions( $item ) );
				return $data;
			},
			$this->getGuardFiles()->getItemsForSlug( $item->asset_type === 'plugin' ? $item->file : $item->stylesheet )
		);
	}

	private function getActions( Scans\Ptg\ResultItem $item ) {
		$con = $this->getCon();

		$actions = [];

		$defaultButtonClasses = [
			'btn',
			'action',
		];
		if ( $item->is_different ) {
			$actions[] = sprintf( '<button class="btn-warning repair %s" title="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'Repair', 'wp-simple-firewall' ),
				$item->record_id,
				$con->svgs->raw( 'bootstrap/tools.svg' )
			);
		}
		elseif ( $item->is_unrecognised ) {
			$actions[] = sprintf( '<button class="btn-danger delete %s" title="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'Delete', 'wp-simple-firewall' ),
				$item->record_id,
				$con->svgs->raw( 'bootstrap/x-square.svg' )
			);
		}
		elseif ( $item->is_missing ) {
			$actions[] = sprintf( '<button class="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				$item->record_id,
				'Restore'
			);
		}

		if ( $item->is_different || $item->is_unrecognised ) {
			$actions[] = sprintf( '<button class="btn-dark download %s" title="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'Download', 'wp-simple-firewall' ),
				$item->record_id,
				$con->svgs->raw( 'bootstrap/download.svg' )
			);
		}

		$actions[] = sprintf( '<button class="btn-light ignore %s" title="%s" data-rid="%s">%s</button>',
			implode( ' ', $defaultButtonClasses ),
			__( 'Ignore', 'wp-simple-firewall' ),
			$item->record_id,
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
}