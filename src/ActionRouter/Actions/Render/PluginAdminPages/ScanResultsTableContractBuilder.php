<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Render\Components,
	Actions\ScanResultsTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\{
	ForMalware,
	ForPluginTheme,
	ForWordpress
};

class ScanResultsTableContractBuilder {

	private ScanResultsScopeResolver $scopeResolver;
	private ScanResultsDisplayOptions $displayOptions;

	public function __construct( ?ScanResultsScopeResolver $scopeResolver = null, ?ScanResultsDisplayOptions $displayOptions = null ) {
		$this->scopeResolver = $scopeResolver ?? new ScanResultsScopeResolver();
		$this->displayOptions = $displayOptions ?? new ScanResultsDisplayOptions();
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildFileStatus(
		string $subjectType,
		string $subjectId,
		string $fullLogHref,
		array $scanResultsActionData = []
	) :array {
		$subjectType = \strtolower( \trim( $subjectType ) );
		$subjectId = \trim( $subjectId );
		$tableActionData = \array_merge(
			$this->scopeResolver->canonicalActionDataForSubject( $subjectType, $subjectId ),
			$scanResultsActionData
		);
		$tableActionData = $this->displayOptions->mergeIntoActionData(
			$tableActionData,
			$this->displayOptions->explicitOptionsFromActionData( $tableActionData )
		);

		switch ( $subjectType ) {
			case InvestigationTableContract::SUBJECT_TYPE_CORE:
				$datatablesInit = ( new ForWordpress() )->buildRaw();
				break;
			case InvestigationTableContract::SUBJECT_TYPE_PLUGIN:
			case InvestigationTableContract::SUBJECT_TYPE_THEME:
			default:
				$datatablesInit = ( new ForPluginTheme() )->buildRaw();
				break;
		}

		return $this->buildTableContract(
			__( 'File Scan Status', 'wp-simple-firewall' ),
			'warning',
			'file-status-'.$subjectType.'-'.$subjectId,
			$datatablesInit,
			ActionData::Build( ScanResultsTableAction::class, true, $tableActionData ),
			$fullLogHref,
			__( 'Full Scan Results', 'wp-simple-firewall' )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildFileStatusWithEmptyState(
		string $subjectType,
		string $subjectId,
		int $resultCount,
		string $emptyText,
		string $fullLogHref,
		string $emptyStatus = 'info',
		array $scanResultsActionData = []
	) :array {
		return $this->withEmptyState(
			$this->buildFileStatus( $subjectType, $subjectId, $fullLogHref, $scanResultsActionData ),
			$resultCount,
			$emptyText,
			$emptyStatus
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildMalware( string $fullLogHref, array $scanResultsActionData = [] ) :array {
		$tableActionData = $this->displayOptions->mergeIntoActionData(
			\array_merge(
				$this->scopeResolver->normalizeActionScope( 'malware', 'malware' ),
				$scanResultsActionData
			),
			$this->displayOptions->explicitOptionsFromActionData( $scanResultsActionData )
		);

		return $this->buildTableContract(
			__( 'Malware Results', 'wp-simple-firewall' ),
			'warning',
			'malware',
			( new ForMalware() )->buildRaw(),
			ActionData::Build( ScanResultsTableAction::class, true, $tableActionData ),
			$fullLogHref,
			__( 'Full Scan Results', 'wp-simple-firewall' )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildMalwareWithEmptyState(
		int $resultCount,
		string $emptyText,
		string $fullLogHref,
		string $emptyStatus = 'info',
		array $scanResultsActionData = []
	) :array {
		return $this->withEmptyState(
			$this->buildMalware( $fullLogHref, $scanResultsActionData ),
			$resultCount,
			$emptyText,
			$emptyStatus
		);
	}

	/**
	 * @param array<string,mixed> $datatablesInit
	 * @param array<string,mixed> $tableAction
	 * @return array<string,mixed>
	 */
	private function buildTableContract(
		string $title,
		string $status,
		string $tableKey,
		array $datatablesInit,
		array $tableAction,
		string $fullLogHref,
		string $fullLogText
	) :array {
		return [
			'title'                     => $title,
			'status'                    => $status,
			'table_id'                  => 'ShieldScanResultsTable-'.\substr( \md5( $tableKey ), 0, 12 ),
			'datatables_init_attr'      => $this->encodeJsonAttr( $datatablesInit ),
			'table_action_attr'         => $this->encodeJsonAttr( $tableAction ),
			'results_display_options_attr' => $this->encodeJsonAttr( $tableAction[ 'results_display_options' ] ),
			'render_item_analysis_attr' => $this->encodeJsonAttr(
				ActionData::BuildAjaxRender( Components\Scans\ItemAnalysis\Container::class )
			),
			'full_log_href'             => $fullLogHref,
			'full_log_text'             => $fullLogText,
			'full_log_button_class'     => 'btn btn-primary btn-sm',
			'show_header'               => false,
			'is_flat'                   => true,
			'is_empty'                  => false,
			'empty_status'              => 'info',
			'empty_text'                => '',
		];
	}

	/**
	 * @param array<string,mixed> $table
	 * @return array<string,mixed>
	 */
	private function withEmptyState( array $table, int $count, string $emptyText, string $emptyStatus ) :array {
		if ( $count > 0 ) {
			return $table;
		}

		unset(
			$table[ 'table_id' ],
			$table[ 'datatables_init_attr' ],
			$table[ 'table_action_attr' ],
			$table[ 'render_item_analysis_attr' ]
		);
		$table[ 'is_empty' ] = true;
		$table[ 'empty_text' ] = $emptyText;
		$table[ 'empty_status' ] = $emptyStatus;

		return $table;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function encodeJsonAttr( array $data ) :string {
		return empty( $data ) ? '' : ( \is_string( $encoded = \json_encode( $data ) ) ? $encoded : '' );
	}
}
