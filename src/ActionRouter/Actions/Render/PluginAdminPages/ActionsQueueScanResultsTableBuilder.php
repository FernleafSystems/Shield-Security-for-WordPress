<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type ScanResultsTableContract from ScanResultsTableContractBuilder
 */
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
	 * @phpstan-return ScanResultsTableContract
	 */
	public function buildWordpressTable( ?array $options = null ) :array {
		return $this->tableContractBuilder->buildFileStatus(
			InvestigationTableContract::SUBJECT_TYPE_CORE,
			InvestigationTableContract::SUBJECT_TYPE_CORE,
			$this->buildFullLogHref(),
			$this->buildTableActionData( $options )
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @phpstan-return ScanResultsTableContract
	 */
	public function buildPluginTable( string $pluginFile, ?array $options = null ) :array {
		return $this->tableContractBuilder->buildFileStatus(
			InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			$pluginFile,
			$this->buildFullLogHref(),
			$this->buildTableActionData( $options )
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @phpstan-return ScanResultsTableContract
	 */
	public function buildThemeTable( string $stylesheet, ?array $options = null ) :array {
		return $this->tableContractBuilder->buildFileStatus(
			InvestigationTableContract::SUBJECT_TYPE_THEME,
			$stylesheet,
			$this->buildFullLogHref(),
			$this->buildTableActionData( $options )
		);
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @phpstan-return ScanResultsTableContract
	 */
	public function buildMalwareTable( ?array $options = null ) :array {
		return $this->tableContractBuilder->buildMalware(
			$this->buildFullLogHref(),
			$this->buildTableActionData( $options )
		);
	}

	protected function buildFullLogHref() :string {
		return self::con()->plugin_urls->actionsQueueScans();
	}

	/**
	 * @param array<string,mixed>|null $options
	 * @return array{
	 *   display_context:string,
	 *   scan_results_notice_context:string,
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
		$actionData = $normalized === $this->displayOptions->activeOnly()
			? $this->displayOptions->buildDisplayContextActionData()
			: $this->displayOptions->buildExplicitActionData( $normalized );

		$actionData[ 'scan_results_notice_context' ] = ActionsQueueScanResultScopeStateBuilder::NOTICE_CONTEXT_ACTIONS_QUEUE;
		return $actionData;
	}
}
