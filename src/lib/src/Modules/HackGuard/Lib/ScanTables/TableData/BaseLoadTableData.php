<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\TableData;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\FormatBytes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_by
 * @property string   $order_dir
 * @property string   $search_text
 */
abstract class BaseLoadTableData extends DynPropertiesClass {

	use ModConsumer;

	abstract public function run() :array;

	public function countAll() :int {
		return $this->getRecordCounter()->count();
	}

	protected function getRecordCounter() :RetrieveCount {
		$retriever = $this->getRecordRetriever();
		return ( new RetrieveCount() )
			->setMod( $this->getMod() )
			->setScanController( $retriever->getScanController() )
			->addWheres( $retriever->getWheres() );
	}

	protected function getRecordRetriever() :RetrieveItems {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$retriever = ( new RetrieveItems() )
			->setMod( $this->getMod() )
			->setScanController( $mod->getScanCon( Afs::SCAN_SLUG ) );
		$retriever->limit = $this->limit;
		$retriever->offset = $this->offset;

		if ( !empty( $this->order_by ) ) {
			switch ( $this->order_by ) {
				case 'created_at':
					$by = '`ri`.`created_at`';
					break;
				case 'file':
					$by = '`ri`.`item_id`';
					break;
				default:
					$by = null;
					break;
			}

			$retriever->order_by = $by;
			if ( !empty( $by ) && in_array( strtoupper( (string)$this->order_dir ), [ 'ASC', 'DESC' ] ) ) {
				$retriever->order_dir = $this->order_dir;
			}
		}

		if ( !empty( $this->search_text ) ) {
			$retriever->addWheres( [
				sprintf( "`ri`.`item_id` LIKE '%%%s%%'", $this->search_text )
			] );
		}

		return $retriever;
	}

	/**
	 * @param Scans\Base\ResultItem $item
	 * @throws \Exception
	 */
	protected function getActions( string $status, $item ) :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$actionHandler = $mod->getScanCon( $item->VO->scan )
							 ->getItemActionHandler()
							 ->setScanItem( $item );

		$actions = [];

		$defaultButtonClasses = [
			'btn',
			'action',
		];

		if ( !empty( $item->path_fragment ) ) {
			$actions[] = sprintf( '<button class="action view-file btn-dark %s" title="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'View File Details', 'wp-simple-firewall' ),
				$item->VO->scanresult_id,
				$con->svgs->raw( 'bootstrap/zoom-in.svg' )
			);
		}

		if ( in_array( $status, [ 'unrecognised', 'malware' ] ) ) {
			$actions[] = sprintf( '<button class="btn-danger delete %s" title="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'Delete', 'wp-simple-firewall' ),
				$item->VO->scanresult_id,
				$con->svgs->raw( 'bootstrap/x-square.svg' )
			);
		}

		try {
			if ( in_array( $status, [ 'modified', 'missing', 'malware' ] )
				 && $actionHandler->getRepairHandler()->canRepairItem() ) {
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

		$actions[] = sprintf( '<button class="btn-light ignore %s" title="%s" data-rid="%s">%s</button>',
			implode( ' ', $defaultButtonClasses ),
			__( 'Ignore', 'wp-simple-firewall' ),
			$item->VO->scanresult_id,
			$con->svgs->raw( 'bootstrap/eye-slash-fill.svg' )
		);

		return $actions;
	}

	protected function getColumnContent_File( Scans\Afs\ResultItem $item ) :string {
		return sprintf( '<div>%s</div>', $this->getColumnContent_FileAsHref( $item ) );
	}

	protected function getColumnContent_FileStatus( Scans\Afs\ResultItem $item, string $status ) :string {
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
					),
					sprintf( '%s: %s', __( 'Type', 'wp-simple-firewall' ), strtoupper( Paths::Ext( $item->path_full ) ) )
				] )
			);
		}

		return $content;
	}

	protected function getColumnContent_FileAsHref( Scans\Afs\ResultItem $item ) :string {
		return sprintf( '<a href="#" title="%s" class="action view-file" data-rid="%s">%s</a>',
			__( 'View File Contents', 'wp-simple-firewall' ),
			$item->VO->scanresult_id,
			$item->path_fragment
		);
	}
}