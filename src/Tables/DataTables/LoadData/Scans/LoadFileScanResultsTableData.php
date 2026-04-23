<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	RetrieveCount,
	RetrieveItems
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalwareStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\RetrieveMalwareMalaiStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Decorate\FormatBytes;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

/**
 * @property int      $limit
 * @property int      $offset
 * @property string[] $wheres
 * @property string   $order_by
 * @property string   $order_dir
 * @property string   $search_text
 * @property array    $custom_record_retriever_wheres
 * @property array<string,mixed>|null $results_display_options
 */
class LoadFileScanResultsTableData extends DynPropertiesClass {

	use PluginControllerConsumer;

	public function run() :array {
		$resultsDisplayOptions = $this->getResultsDisplayOptions();
		$results = $this->getRecordRetriever()->retrieveForResultsTables( $resultsDisplayOptions );

		/**
		 * Bulk update the malai statuses
		 */
		( new RetrieveMalwareMalaiStatus() )->updateRecords(
			\array_map( fn( ResultItem $item ) => $item->getMalwareRecord(), $results->getMalware()->getItems() )
		);

		$changed = false;
		$AFS = self::con()->comps->scans->AFS();
		/** @var ResultItem $item */
		foreach ( $results->getItems() as $item ) {
			$changed = $AFS->cleanStaleResultItem( $item ) || $changed;
		}
		if ( $changed ) {
			$results = $this->getRecordRetriever()->retrieveForResultsTables( $resultsDisplayOptions );
		}

		try {
			$files = \array_map( fn( ResultItem $item ) => $this->getDataFromItem( $item ), $results->getItems() );
		}
		catch ( \Exception $e ) {
			$files = [];
		}
		return $files;
	}

	protected function getDataFromItem( ResultItem $item ) :array {
		$isIgnored = $item->VO->ignored_at > 0;
		$data = \array_merge( $item->getRawData(), [
			'rid'              => $item->VO->resultitem_id,
			'file'             => $item->path_fragment,
			'created_at'       => $item->VO->created_at,
			'detected_since'   => Services::Request()
										  ->carbon( true )
										  ->setTimestamp( $item->VO->created_at )
										  ->diffForHumans(),
			'file_as_href'     => $this->getColumnContent_FileAsHref( $item ),
			'file_type'        => \strtoupper( Services::Data()->getExtension( $item->path_full ) ),
			'status_file_size' => $this->column_fileSize( $item ),
			'status_file_type' => $this->column_fileType( $item ),
			'status'           => $this->getColumnContent_FileStatus( $item ),
			'actions'          => $this->getActionsMarkup( $item ),
			'is_ignored'       => $isIgnored,
			'ignored_label'    => $isIgnored ? __( 'Ignored', 'wp-simple-firewall' ) : '',
			'DT_RowClass'      => $isIgnored ? 'scan-result-row scan-result-row--ignored' : 'scan-result-row',
			'DT_RowAttr'       => [
				'data-scan-result-ignored' => $isIgnored ? '1' : '0',
			],
		] );

		if ( $item->is_mal ) {
			$malRecord = $item->getMalwareRecord();
			if ( !empty( $malRecord ) ) {
				$data[ 'mal_sig' ] = sprintf( '<code style="white-space: nowrap">%s</code>', esc_html( $malRecord->sig ) );
				$data[ 'mal_details' ] = $this->getColumnContent_MalwareDetailsForRecord(
					$item,
					$data[ 'mal_sig' ]
				);
			}
		}

		return $data;
	}

	public function countAll() :int {
		$options = $this->getResultsDisplayOptions();
		return \is_array( $options )
			? $this->getRecordCounter()->countForResultsDisplay( $options )
			: $this->getRecordCounter()->count( RetrieveCount::CONTEXT_RESULTS_DISPLAY );
	}

	protected function getRecordCounter() :RetrieveCount {
		$retriever = $this->getRecordRetriever();
		return ( new RetrieveCount() )
			->setScanController( $retriever->getScanController() )
			->addWheres( $retriever->getWheres() );
	}

	protected function getRecordRetriever() :RetrieveItems {
		$retriever = ( new RetrieveItems() )->setScanController( self::con()->comps->scans->AFS() );
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
			if ( !empty( $by ) && \in_array( \strtoupper( (string)$this->order_dir ), [ 'ASC', 'DESC' ] ) ) {
				$retriever->order_dir = $this->order_dir;
			}
		}

		if ( !empty( $this->search_text ) ) {
			$retriever->addWheres( [
				sprintf( "`ri`.`item_id` LIKE '%%%s%%'", $this->search_text )
			] );
		}

		if ( \is_array( $this->custom_record_retriever_wheres ) ) {
			$retriever->addWheres( $this->custom_record_retriever_wheres );
		}

		return $retriever;
	}

	protected function getActions( ResultItem $item ) :array {
		$con = self::con();
		$actions = [];

		$fileFragment = $item->path_fragment;
		if ( !empty( $fileFragment ) ) {
			$actions[] = $this->buildActionButton(
				'view',
				'view-file',
				$item->VO->resultitem_id,
				__( 'View File Details', 'wp-simple-firewall' ),
				$con->svgs->iconClass( 'zoom-in.svg' )
			);
		}

		if ( !$item->VO->isDeleted() && ( $item->is_unrecognised || $item->is_mal ) ) {
			$actions[] = $this->buildActionButton(
				'delete',
				'delete',
				$item->VO->resultitem_id,
				__( 'Delete', 'wp-simple-firewall' ),
				$con->svgs->iconClass( 'x-square.svg' )
			);
		}

		try {
			if ( !$item->VO->isRepaired() && ( $item->is_checksumfail || $item->is_missing || $item->is_mal ) ) {
				$actionHandler = self::con()
					->comps
					->scans
					->getScanCon( $item->VO->scan )
					->getItemActionHandler()
					->setScanItem( $item );
				if ( $actionHandler->getRepairHandler()->canRepairItem() ) {
					$actions[] = $this->buildActionButton(
						'repair',
						'repair',
						$item->VO->resultitem_id,
						__( 'Repair', 'wp-simple-firewall' ),
						$con->svgs->iconClass( 'tools.svg' )
					);
				}
			}
		}
		catch ( \Exception $e ) {
		}

		if ( $item->VO->ignored_at === 0 ) {
			$actions[] = $this->buildActionButton(
				'ignore',
				'ignore',
				$item->VO->resultitem_id,
				__( 'Ignore', 'wp-simple-firewall' ),
				$con->svgs->iconClass( 'eye-slash-fill.svg' )
			);
		}
		else {
			$actions[] = $this->buildActionButton(
				'unignore',
				'unignore',
				$item->VO->resultitem_id,
				__( 'Unignore', 'wp-simple-firewall' ),
				$con->svgs->iconClass( 'eye-fill.svg' )
			);
		}

		return $actions;
	}

	protected function getActionsMarkup( ResultItem $item ) :string {
		$actions = $this->getActions( $item );

		return empty( $actions )
			? ''
			: sprintf(
				'<div class="scan-results-row-actions">%s</div>',
				\implode( '', $actions )
			);
	}

	protected function column_fileSize( ResultItem $item ) :string {
		$FS = Services::WpFs();
		return $FS->isAccessibleFile( $item->path_full ) ? FormatBytes::Format( $FS->getFileSize( $item->path_full ) ) : '-';
	}

	protected function column_fileType( ResultItem $item ) :string {
		$extension = \strtoupper( Paths::Ext( $item->path_full ) );
		if ( \strpos( $extension, 'PHP' ) !== false ) {
			$type = sprintf( '<img src="%s" width="36px" alt="%s" title="%s" />',
				self::con()->urls->forImage( 'icons/icon-php-elephant.png' ), $extension, $extension );
		}
		elseif ( $extension === 'JS' ) {
			$type = sprintf( '<img src="%s" height="24px" alt="%s" title="%s" />',
				self::con()->urls->forImage( 'icons/icon-javascript.png' ), $extension, $extension );
		}
		elseif ( $extension === 'CSS' ) {
			$type = sprintf( '<img src="%s" height="24px" alt="%s" title="%s" />',
				self::con()->urls->forImage( 'icons/icon-css.png' ), $extension, $extension );
		}
		elseif ( $extension === 'ICO' ) {
			$type = sprintf( '<img src="%s" width="24px" alt="%s" title="%s" />',
				self::con()->urls->forImage( 'icons/icon-ico.png' ), $extension, $extension );
		}
		elseif ( $extension === 'SVG' ) {
			$type = sprintf( '<i class="%s" title="%s" aria-label="%s"></i>',
				self::con()->svgs->iconClass( 'filetype-svg' ), $extension, $extension );
		}
		elseif ( $extension === 'JSON' ) {
			$type = sprintf( '<img src="%s" width="24px" alt="%s" title="%s" />',
				self::con()->urls->forImage( 'icons/icon-json.png' ), $extension, $extension );
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
			\implode( '</li><li>', [
				sprintf( '%s: <span class="badge text-bg-%s">%s</span>',
					__( 'MAL{ai} Malware Status', 'wp-simple-firewall' ),
					$colourStyle,
					( new MalwareStatus() )->nameFromStatusLabel( $record->malai_status )
				),
				sprintf( '%s: %s', __( 'Pattern Detected', 'wp-simple-firewall' ), $sig ),
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
			\implode( '</li><li>', [
				sprintf( '%s: %s', __( 'False Positive Confidence', 'wp-simple-firewall' ), $confidence ),
				sprintf( '%s: %s', __( 'Pattern Detected', 'wp-simple-firewall' ), $sig ),
			] )
		);
	}

	protected function getColumnContent_File( ResultItem $item ) :string {
		return sprintf( '<div>%s</div>', $this->getColumnContent_FileAsHref( $item ) );
	}

	protected function getColumnContent_FileStatus( ResultItem $item ) :string {
		$FS = Services::WpFs();
		$carbon = Services::Request()->carbon( true );

		$content = \implode( ' / ', \array_map( function ( string $status ) {
			return \sprintf( '<span class="badge text-bg-secondary">%s</span>', $status );
		}, $item->getStatusForHuman() ) );

		$meta = [];
		if ( $FS->isAccessibleFile( $item->path_full ) ) {
			$meta[] = \sprintf( '%s: %s', __( 'Modified', 'wp-simple-firewall' ),
				$carbon->setTimestamp( $FS->getModifiedTime( $item->path_full ) )->diffForHumans()
			);
			if ( $this->isOldWpCoreFile( $item ) ) {
				$meta[] = __( 'Obsolete WP core file', 'wp-simple-firewall' );
			}
		}
		elseif ( $item->VO->isDeleted() ) {
			$meta[] = \sprintf( '%s: %s', __( 'Deleted', 'wp-simple-firewall' ),
				$carbon->setTimestamp( $item->VO->resolved_at )->diffForHumans()
			);
		}

		if ( $item->VO->isRepaired() ) {
			$meta[] = \sprintf( '%s: %s', __( 'Repaired', 'wp-simple-firewall' ),
				$carbon->setTimestamp( $item->VO->resolved_at )->diffForHumans()
			);
		}

		if ( $item->VO->ignored_at > 0 ) {
			$meta[] = \sprintf( '%s: %s', __( 'Ignored', 'wp-simple-firewall' ),
				Services::Request()->carbon( true )
						->setTimestamp( $item->VO->ignored_at )
						->diffForHumans()
			);
		}

		if ( !empty( $meta ) ) {
			$content .= sprintf( '<ul style="list-style: square inside"><li>%s</li></ul>', \implode( '</li><li>', $meta ) );
		}

		return $content;
	}

	private function isOldWpCoreFile( ResultItem $item ) :bool {
		$coreFile = path_join( ABSPATH, 'wp-admin/includes/update-core.php' );
		if ( Services::WpFs()->isAccessibleFile( $coreFile ) ) {
			include_once $coreFile;
		}
		global $_old_files;
		return \is_array( $_old_files ) && \in_array( $item->path_fragment, $_old_files, true );
	}

	protected function getColumnContent_FileAsHref( ResultItem $item ) :string {
		return sprintf(
			'<div class="scan-results-file-cell" data-scan-result-file-cell="1"><a href="#" title="%s" class="action view-file" data-rid="%s" data-scan-result-action="view">%s</a>%s</div>',
			__( 'View File Contents', 'wp-simple-firewall' ),
			$item->VO->resultitem_id,
			esc_html( $item->path_fragment ),
			$item->VO->ignored_at > 0
				? sprintf(
					' <span class="badge text-bg-secondary scan-results-ignored-badge" data-scan-result-ignored-badge="1">%s</span>',
					esc_html__( 'Ignored', 'wp-simple-firewall' )
				)
				: ''
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function getResultsDisplayOptions() :?array {
		return \is_array( $this->results_display_options )
			? $this->results_display_options
			: null;
	}

	private function buildActionButton(
		string $actionKey,
		string $actionClass,
		int $scanResultId,
		string $label,
		string $iconClass
	) :string {
		return sprintf(
			'<button type="button" class="btn btn-sm btn-light action actions-landing__table-icon-action scan-results-row-action scan-results-row-action--%1$s %2$s" title="%3$s" aria-label="%3$s" data-bs-toggle="tooltip" data-bs-title="%3$s" data-rid="%4$s" data-scan-result-action="%1$s"><i class="%5$s" aria-hidden="true"></i><span class="visually-hidden">%3$s</span></button>',
			esc_attr( $actionKey ),
			esc_attr( $actionClass ),
			esc_attr( $label ),
			$scanResultId,
			esc_attr( $iconClass )
		);
	}
}
