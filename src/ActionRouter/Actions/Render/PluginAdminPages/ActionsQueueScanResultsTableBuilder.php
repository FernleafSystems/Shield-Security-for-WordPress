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
	 * @return array<string,mixed>
	 */
	public function buildWordpressTable( ?array $options = null ) :array {
		return $this->tableContractBuilder->buildFileStatus(
			'core',
			'core',
			$this->buildFullLogHref(),
			$this->buildTableActionData( $options )
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array<string,mixed>
	 */
	public function buildPluginTable( string $pluginFile, ?array $options = null ) :array {
		return $this->tableContractBuilder->buildFileStatus(
			'plugin',
			$pluginFile,
			$this->buildFullLogHref(),
			$this->buildTableActionData( $options )
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array<string,mixed>
	 */
	public function buildThemeTable( string $stylesheet, ?array $options = null ) :array {
		return $this->tableContractBuilder->buildFileStatus(
			'theme',
			$stylesheet,
			$this->buildFullLogHref(),
			$this->buildTableActionData( $options )
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array<string,mixed>
	 */
	public function buildMalwareTable( ?array $options = null ) :array {
		return $this->tableContractBuilder->buildMalware(
			$this->buildFullLogHref(),
			$this->buildTableActionData( $options )
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

	/**
	 * @param array<string,mixed>|null $options
	 * @return array{
	 *   display_context:string,
	 *   results_display_options:array{
	 *     include_ignored:bool,
	 *     include_repaired:bool,
	 *     include_deleted:bool,
	 *     ignored_only:bool
	 *   }
	 * }
	 */
	private function buildTableActionData( ?array $options = null ) :array {
		$normalized = $this->displayOptions->normalize( $options ?? $this->displayOptions->activeOnly() );
		return $normalized === $this->displayOptions->activeOnly()
			? $this->displayOptions->buildDisplayContextActionData()
			: $this->displayOptions->buildExplicitActionData( $normalized );
	}
}
