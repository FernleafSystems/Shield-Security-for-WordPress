<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;

class ActionsQueueScanResultsTableBuilder {

	use PluginControllerConsumer;

	private ScanResultsScopeResolver $scopeResolver;
	private ScanResultsDisplayOptions $displayOptions;
	private ScanResultsTableContractBuilder $tableContractBuilder;

	public function __construct(
		?ScanResultsScopeResolver $scopeResolver = null,
		?ScanResultsDisplayOptions $displayOptions = null,
		?ScanResultsTableContractBuilder $tableContractBuilder = null
	) {
		$this->scopeResolver = $scopeResolver ?? new ScanResultsScopeResolver();
		$this->displayOptions = $displayOptions ?? new ScanResultsDisplayOptions();
		$this->tableContractBuilder = $tableContractBuilder ?? new ScanResultsTableContractBuilder(
			$this->scopeResolver,
			$this->displayOptions
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array{
	 *   display_context:string,
	 *   type:string,
	 *   file:string,
	 *   results_display_options:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
	 * }
	 */
	public function buildScopeActionData( string $type, string $file, ?array $options = null ) :array {
		$scope = $this->scopeResolver->normalizeActionScope( $type, $file );
		return $this->displayOptions->mergeIntoActionData(
			$scope,
			$options ?? $this->displayOptions->activeOnly()
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array<string,mixed>
	 */
	public function buildTableForScope( string $type, string $file, string $emptyText, ?array $options = null ) :array {
		$scope = $this->scopeResolver->normalizeActionScope( $type, $file );
		$displayOptions = $this->displayOptions->normalize( $options ?? $this->displayOptions->activeOnly() );
		$actionData = $this->buildScopeActionData( $scope[ 'type' ], $scope[ 'file' ], $displayOptions );
		$livenessCount = $this->countForScope(
			$scope[ 'type' ],
			$scope[ 'file' ],
			$this->displayOptions->activeAndIgnored()
		);

		if ( $scope[ 'type' ] === 'malware' ) {
			return $this->tableContractBuilder->buildMalwareWithEmptyState(
				$livenessCount,
				$emptyText,
				$this->buildFullLogHref(),
				'info',
				$actionData
			);
		}

		return $this->tableContractBuilder->buildFileStatusWithEmptyState(
			$scope[ 'type' ] === 'wordpress' ? 'core' : $scope[ 'type' ],
			$scope[ 'type' ] === 'wordpress' ? 'core' : $scope[ 'file' ],
			$livenessCount,
			$emptyText,
			$this->buildFullLogHref(),
			'info',
			$actionData
		);
	}

	/**
	 * @param array<string,mixed> $options
	 */
	public function countForScope( string $type, string $file, array $options ) :int {
		$scope = $this->scopeResolver->normalizeActionScope( $type, $file );
		$loader = new LoadFileScanResultsTableData();
		$loader->custom_record_retriever_wheres = $this->scopeResolver->wheresForActionScope(
			$scope[ 'type' ],
			$scope[ 'file' ]
		);
		$loader->results_display_options = $this->displayOptions->normalize( $options );
		return $loader->countAll();
	}

	protected function buildFullLogHref() :string {
		return self::con()->plugin_urls->actionsQueueScans();
	}
}
