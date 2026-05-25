<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Render\Components,
	Actions\ScanResultsTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\{
	ForMalware,
	ForPluginTheme,
	ForWordpress
};

/**
 * @phpstan-import-type ScanResultsDisplayNotice from ActionsQueueScanResultScopeStateBuilder
 * @phpstan-type ScanResultsTableContract array{
 *   title:string,
 *   status:string,
 *   table_id?:string,
 *   datatables_init_attr?:string,
 *   table_action_attr?:string,
 *   results_display_options_attr:string,
 *   render_item_analysis_attr?:string,
 *   full_log_href:string,
 *   full_log_text:string,
 *   full_log_button_class:string,
 *   display_notice:ScanResultsDisplayNotice,
 *   show_header:bool,
 *   is_flat:bool,
 *   is_empty:bool,
 *   empty_status:string,
 *   empty_text:string
 * }
 */
class ScanResultsTableContractBuilder {

	private ScanResultsScopeResolver $scopeResolver;
	private ScanResultsDisplayOptions $displayOptions;
	private ActionsQueueScanResultScopeStateBuilder $scopeStateBuilder;

	public function __construct(
		?ScanResultsScopeResolver $scopeResolver = null,
		?ScanResultsDisplayOptions $displayOptions = null,
		?ActionsQueueScanResultScopeStateBuilder $scopeStateBuilder = null
	) {
		$this->scopeResolver = $scopeResolver ?? new ScanResultsScopeResolver();
		$this->displayOptions = $displayOptions ?? new ScanResultsDisplayOptions();
		$this->scopeStateBuilder = $scopeStateBuilder ?? new ActionsQueueScanResultScopeStateBuilder(
			$this->scopeResolver,
			$this->displayOptions
		);
	}

	/**
	 * @phpstan-return ScanResultsTableContract
	 * @throws \InvalidArgumentException
	 */
	public function buildFileStatus(
		string $subjectType,
		string $subjectId,
		string $fullLogHref,
		array $scanResultsActionData = []
	) :array {
		$scope = $this->scopeResolver->canonicalActionDataForSubject( $subjectType, $subjectId );
		$tableActionData = \array_merge( $scanResultsActionData, $scope );
		$tableActionData = $this->displayOptions->mergeIntoActionData(
			$tableActionData,
			$this->displayOptions->explicitOptionsFromActionData( $tableActionData )
		);

		switch ( $scope[ 'type' ] ) {
			case ScanResultsScopeResolver::SCOPE_TYPE_WORDPRESS:
				$datatablesInit = ( new ForWordpress() )->buildRaw();
				break;
			case ScanResultsScopeResolver::SCOPE_TYPE_PLUGIN:
			case ScanResultsScopeResolver::SCOPE_TYPE_THEME:
				$datatablesInit = ( new ForPluginTheme() )->buildRaw();
				break;
			default:
				throw new \InvalidArgumentException( \sprintf( 'Unsupported scan result scope type "%s".', $scope[ 'type' ] ) );
		}

		return $this->buildTableContract(
			__( 'File Scan Status', 'wp-simple-firewall' ),
			'warning',
			'file-status-'.$scope[ 'type' ].'-'.$scope[ 'file' ],
			$datatablesInit,
			ActionData::Build( ScanResultsTableAction::class, true, $tableActionData ),
			$fullLogHref,
			__( 'Full Scan Results', 'wp-simple-firewall' ),
			$this->buildDisplayNotice( $scope, $tableActionData )
		);
	}

	/**
	 * @phpstan-return ScanResultsTableContract
	 * @throws \InvalidArgumentException
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
	 * @phpstan-return ScanResultsTableContract
	 */
	public function buildMalware( string $fullLogHref, array $scanResultsActionData = [] ) :array {
		$scope = $this->scopeResolver->normalizeActionScope(
			ScanResultsScopeResolver::SCOPE_TYPE_MALWARE,
			ScanResultsScopeResolver::SCOPE_TYPE_MALWARE
		);
		$tableActionData = \array_merge( $scanResultsActionData, $scope );
		$tableActionData = $this->displayOptions->mergeIntoActionData(
			$tableActionData,
			$this->displayOptions->explicitOptionsFromActionData( $tableActionData )
		);

		return $this->buildTableContract(
			__( 'Malware Results', 'wp-simple-firewall' ),
			'warning',
			ScanResultsScopeResolver::SCOPE_TYPE_MALWARE,
			( new ForMalware() )->buildRaw(),
			ActionData::Build( ScanResultsTableAction::class, true, $tableActionData ),
			$fullLogHref,
			__( 'Full Scan Results', 'wp-simple-firewall' ),
			$this->buildDisplayNotice( $scope, $tableActionData )
		);
	}

	/**
	 * @param array<string,mixed> $datatablesInit
	 * @param array<string,mixed> $tableAction
	 * @phpstan-param ScanResultsDisplayNotice $displayNotice
	 * @phpstan-return ScanResultsTableContract
	 */
	private function buildTableContract(
		string $title,
		string $status,
		string $tableKey,
		array $datatablesInit,
		array $tableAction,
		string $fullLogHref,
		string $fullLogText,
		array $displayNotice
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
			'display_notice'            => $displayNotice,
			'show_header'               => false,
			'is_flat'                   => true,
			'is_empty'                  => false,
			'empty_status'              => 'info',
			'empty_text'                => '',
		];
	}

	/**
	 * @phpstan-param ScanResultsTableContract $table
	 * @phpstan-return ScanResultsTableContract
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

	/**
	 * @param array{type:string,file:string} $scope
	 * @param array<string,mixed>            $tableActionData
	 * @phpstan-return ScanResultsDisplayNotice
	 */
	private function buildDisplayNotice( array $scope, array $tableActionData ) :array {
		if ( (string)( $tableActionData[ 'scan_results_notice_context' ] ?? '' )
			 !== ActionsQueueScanResultScopeStateBuilder::NOTICE_CONTEXT_ACTIONS_QUEUE ) {
			return $this->scopeStateBuilder->hiddenDisplayNotice();
		}

		return $this->scopeStateBuilder->buildForActionScope(
			$scope[ 'type' ],
			$scope[ 'file' ],
			$this->displayOptions->currentOptionsFromActionData( $tableActionData ),
			true
		)[ 'display_notice' ];
	}
}
