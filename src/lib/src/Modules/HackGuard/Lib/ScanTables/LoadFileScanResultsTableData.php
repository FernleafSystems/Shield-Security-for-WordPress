<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItemMeta\Ops as ResultItemMetaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\CreateLocalMalwareRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalwareStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\RetrieveMalwareMalaiStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\FormatBytes;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_by
 * @property string   $order_dir
 * @property string   $search_text
 * @property array    $custom_record_retriever_wheres
 */
class LoadFileScanResultsTableData extends DynPropertiesClass {

	use ModConsumer;

	public function run() :array {
		$results = $this->getRecordRetriever()->retrieveForResultsTables();

		/**
		 * Bulk update the malai statuses
		 */
		( new RetrieveMalwareMalaiStatus() )->updateRecords(
			array_map( function ( ResultItem $item ) {
				return $item->getMalwareRecord();
			}, $results->getMalware()->getItems() )
		);

		try {
			$files = array_map(
				function ( ResultItem $item ) {
					return $this->getDataFromItem( $item );
				},
				$results->getItems()
			);
		}
		catch ( \Exception $e ) {
			$files = [];
		}
		return $files;
	}

	protected function getDataFromItem( ResultItem $item ) :array {
		$data = array_merge( $item->getRawData(), [
			'rid'              => $item->VO->scanresult_id,
			'file'             => $item->path_fragment,
			'created_at'       => $item->VO->created_at,
			'detected_since'   => Services::Request()
										  ->carbon( true )
										  ->setTimestamp( $item->VO->created_at )
										  ->diffForHumans(),
			'file_as_href'     => $this->getColumnContent_FileAsHref( $item ),
			'file_type'        => strtoupper( Services::Data()->getExtension( $item->path_full ) ),
			'status_file_size' => $this->column_fileSize( $item ),
			'status_file_type' => $this->column_fileType( $item ),
			'status'           => $this->getColumnContent_FileStatus( $item ),
			'actions'          => implode( ' ', $this->getActions( $item ) ),
		] );

		if ( $item->is_mal ) {
			$malRecord = $item->getMalwareRecord();

			if ( empty( $malRecord ) && !empty( $data[ 'mal_sig' ] ) ) {
				try {
					/**
					 * @deprecated 18.0: this is used to update old malware scan results to use the newer Malware Records DB
					 */
					$malRecord = ( new CreateLocalMalwareRecords() )->run( $item->path_fragment, $data[ 'mal_sig' ] );
					$item->malware_record_id = $malRecord->id;
					/** @var ResultItemMetaDB\Insert $metaInserter */
					$metaInserter = $this->mod()->getDbH_ResultItemMeta()->getQueryInserter();
					$metaInserter->setInsertData( [
						'ri_ref'     => $item->VO->resultitem_id,
						'meta_key'   => 'malware_record_id',
						'meta_value' => $item->malware_record_id,
					] )->query();
				}
				catch ( \Exception $e ) {
				}
			}

			if ( !empty( $malRecord ) ) {
				$data[ 'mal_sig' ] = sprintf( '<code style="white-space: nowrap">%s</code>', esc_html( $malRecord->sig ) );
				$data[ 'mal_details' ] = $this->getColumnContent_MalwareDetailsForRecord(
					$item,
					$data[ 'mal_sig' ]
				);
			}
			else {
				$data[ 'mal_sig' ] = sprintf( '<code style="white-space: nowrap">%s</code>', esc_html( $item->mal_sig ) );
				/** @deprecated 17.1 */
				$data[ 'mal_details' ] = $this->getColumnContent_MalwareDetails(
					(int)$item->mal_fp_confidence,
					$data[ 'mal_sig' ]
				);
			}
		}

		return $data;
	}

	public function countAll() :int {
		return $this->getRecordCounter()->count();
	}

	protected function getRecordCounter() :RetrieveCount {
		$retriever = $this->getRecordRetriever();
		return ( new RetrieveCount() )
			->setScanController( $retriever->getScanController() )
			->addWheres( $retriever->getWheres() );
	}

	protected function getRecordRetriever() :RetrieveItems {
		$retriever = ( new RetrieveItems() )->setScanController( $this->mod()->getScansCon()->AFS() );
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

		if ( is_array( $this->custom_record_retriever_wheres ) ) {
			$retriever->addWheres( $this->custom_record_retriever_wheres );
		}

		return $retriever;
	}

	protected function getActions( ResultItem $item ) :array {
		$con = $this->con();
		$actions = [];

		$defaultButtonClasses = [
			'btn',
			'action',
		];

		$fileFragment = $item->path_fragment;
		if ( !empty( $fileFragment ) ) {
			$actions[] = sprintf( '<button class="action view-file btn-dark %s" title="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'View File Details', 'wp-simple-firewall' ),
				$item->VO->scanresult_id,
				$con->svgs->raw( 'zoom-in.svg' )
			);
		}

		if ( $item->is_unrecognised || $item->is_mal ) {
			$actions[] = sprintf( '<button class="btn-danger delete %s" title="%s" data-rid="%s">%s</button>',
				implode( ' ', $defaultButtonClasses ),
				__( 'Delete', 'wp-simple-firewall' ),
				$item->VO->scanresult_id,
				$con->svgs->raw( 'x-square.svg' )
			);
		}

		try {
			if ( $item->is_checksumfail || $item->is_missing || $item->is_mal ) {
				$actionHandler = $this->mod()
									  ->getScansCon()
									  ->getScanCon( $item->VO->scan )
									  ->getItemActionHandler()
									  ->setScanItem( $item );
				if ( $actionHandler->getRepairHandler()->canRepairItem() ) {
					$actions[] = sprintf( '<button class="btn-warning repair %s" title="%s" data-rid="%s">%s</button>',
						implode( ' ', $defaultButtonClasses ),
						__( 'Repair', 'wp-simple-firewall' ),
						$item->VO->scanresult_id,
						$con->svgs->raw( 'tools.svg' )
					);
				}
			}
		}
		catch ( \Exception $e ) {
		}

		$actions[] = sprintf( '<button class="btn-light ignore %s" title="%s" data-rid="%s">%s</button>',
			implode( ' ', $defaultButtonClasses ),
			__( 'Ignore', 'wp-simple-firewall' ),
			$item->VO->scanresult_id,
			$con->svgs->raw( 'eye-slash-fill.svg' )
		);

		return $actions;
	}

	protected function column_fileSize( ResultItem $item ) :string {
		$FS = Services::WpFs();
		return $FS->isAccessibleFile( $item->path_full ) ?
			FormatBytes::Format( $FS->getFileSize( $item->path_full ) ) : '-';
	}

	protected function column_fileType( ResultItem $item ) :string {
		$extension = strtoupper( Paths::Ext( $item->path_full ) );
		if ( strpos( $extension, 'PHP' ) !== false ) {
			$type = sprintf( '<img src="%s" width="36px" alt="%s" title="%s" />',
				$this->con()->urls->forImage( 'icons/icon-php-elephant.png' ), $extension, $extension );
		}
		elseif ( $extension === 'JS' ) {
			$type = sprintf( '<img src="%s" height="24px" alt="%s" title="%s" />',
				$this->con()->urls->forImage( 'icons/icon-javascript.png' ), $extension, $extension );
		}
		elseif ( $extension === 'CSS' ) {
			$type = sprintf( '<img src="%s" height="24px" alt="%s" title="%s" />',
				$this->con()->urls->forImage( 'icons/icon-css.png' ), $extension, $extension );
		}
		elseif ( $extension === 'ICO' ) {
			$type = sprintf( '<img src="%s" width="24px" alt="%s" title="%s" />',
				$this->con()->urls->forImage( 'icons/icon-ico.png' ), $extension, $extension );
		}
		elseif ( $extension === 'SVG' ) {
			$type = sprintf( '<img src="%s" width="24px" alt="%s" title="%s" />',
				$this->con()->urls->svg( 'filetype-svg' ), $extension, $extension );
		}
		elseif ( $extension === 'JSON' ) {
			$type = sprintf( '<img src="%s" width="24px" alt="%s" title="%s" />',
				$this->con()->urls->forImage( 'icons/icon-json.png' ), $extension, $extension );
		}
		else {
			$type = $extension;
		}
		return $type;
	}

	protected function getColumnContent_MalwareDetailsForRecord( ResultItem $item, string $sig ) :string {
		$record = $item->getMalwareRecord();

		switch ( $record->malai_status ) {
			case MalwareStatus::STATUS_MALWARE:
			case MalwareStatus::STATUS_PREDICTED_MALWARE:
				$colourStyle = 'danger';
				break;
			case MalwareStatus::STATUS_CLEAN:
			case MalwareStatus::STATUS_FP:
				$colourStyle = 'success';
				break;
			default:
				$colourStyle = 'warning';
				break;
		}

		return sprintf( '<ul style="list-style: square inside"><li>%s</li></ul>',
			implode( '</li><li>', [
				sprintf( '%s: <span class="badge text-bg-%s">%s</span>',
					__( 'MAL{ai} Malware Status' ),
					$colourStyle,
					( new MalwareStatus() )->nameFromStatusLabel( $record->malai_status )
				),
				sprintf( '%s: %s', __( 'Pattern Detected' ), $sig ),
				sprintf( '%s: %s', __( 'Modified', 'wp-simple-firewall' ),
					Services::Request()
							->carbon()
							->setTimestamp( Services::WpFs()->getModifiedTime( $item->path_full ) )
							->diffForHumans()
				)
			] )
		);
	}

	protected function getColumnContent_MalwareDetails( int $confidence, string $sig ) :string {
		return sprintf( '<ul style="list-style: square inside"><li>%s</li></ul>',
			implode( '</li><li>', [
				sprintf( '%s: %s', __( 'False Positive Confidence' ), $confidence ),
				sprintf( '%s: %s', __( 'Pattern Detected' ), $sig ),
			] )
		);
	}

	protected function getColumnContent_File( ResultItem $item ) :string {
		return sprintf( '<div>%s</div>', $this->getColumnContent_FileAsHref( $item ) );
	}

	protected function getColumnContent_FileStatus( ResultItem $item ) :string {
		$FS = Services::WpFs();

		if ( $FS->isFile( $item->path_full ) ) {
			$carbon = Services::Request()->carbon( true );
			$content = sprintf( '%s<ul style="list-style: square inside"><li>%s</li></ul>',
				sprintf( '<span class="badge text-bg-secondary">%s</span>', $item->getStatusForHuman() ),
				implode( '</li><li>', [
					sprintf( '%s: %s', __( 'Modified', 'wp-simple-firewall' ),
						$carbon->setTimestamp( $FS->getModifiedTime( $item->path_full ) )
							   ->diffForHumans()
					)
				] )
			);
		}
		else {
			$content = $item->getStatusForHuman();
		}

		return $content;
	}

	protected function getColumnContent_FileAsHref( ResultItem $item ) :string {
		return sprintf( '<a href="#" title="%s" class="action view-file" data-rid="%s">%s</a>',
			__( 'View File Contents', 'wp-simple-firewall' ),
			$item->VO->scanresult_id,
			esc_html( $item->path_fragment )
		);
	}
}