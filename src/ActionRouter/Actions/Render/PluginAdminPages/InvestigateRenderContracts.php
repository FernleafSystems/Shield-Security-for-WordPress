<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\InvestigateLookupSelect,
	Actions\InvestigationTableAction,
	Actions\Render\Components,
	Actions\ScanResultsTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @phpstan-type LookupBehaviorContract array{
 *   panel_form:bool,
 *   use_select2:bool,
 *   auto_submit_on_change:bool
 * }
 * @phpstan-type LookupRouteContract array{
 *   page:string,
 *   nav:string,
 *   nav_sub:string
 * }
 * @phpstan-type LookupAjaxContract array{
 *   subject:string,
 *   minimum_input_length:int,
 *   delay_ms:int,
 *   action:array<string,mixed>
 * }
 * @phpstan-type InvestigationTableContract array{
 *   title:string,
 *   status:string,
 *   full_log_text:string,
 *   full_log_button_class:string,
 *   show_header:bool,
 *   is_flat:bool,
 *   is_empty:bool,
 *   empty_status:string,
 *   empty_text:string
 * }&array<string,mixed>
 * @phpstan-type InvestigationTableContractInput array{
 *   title:string,
 *   status:string,
 *   table_type?:string,
 *   subject_type?:string,
 *   subject_id?:string,
 *   datatables_init?:array<string,mixed>,
 *   table_action?:array<string,mixed>,
 *   full_log_href?:string,
 *   full_log_text?:string,
 *   full_log_button_class?:string,
 *   show_header?:bool,
 *   scan_results_action?:array<string,mixed>,
 *   render_item_analysis?:array<string,mixed>,
 *   is_flat?:bool,
 *   is_empty?:bool,
 *   empty_status?:string,
 *   empty_text?:string
 * }
 */
trait InvestigateRenderContracts {

	use PluginControllerConsumer;

	/**
	 * @return LookupBehaviorContract
	 */
	protected function buildLookupBehaviorContract(
		bool $panelForm = true,
		bool $useSelect2 = false,
		bool $autoSubmitOnChange = false
	) :array {
		return [
			'panel_form'            => $panelForm,
			'use_select2'           => $useSelect2,
			'auto_submit_on_change' => $autoSubmitOnChange,
		];
	}

	/**
	 * @return LookupRouteContract
	 */
	protected function buildLookupRouteContract( string $subNav ) :array {
		return [
			'page'    => self::con()->plugin_urls->rootAdminPageSlug(),
			'nav'     => PluginNavs::NAV_ACTIVITY,
			'nav_sub' => $subNav,
		];
	}

	/**
	 * @return LookupAjaxContract
	 */
	protected function buildLookupAjaxContract( string $subject, int $minimumInputLength = 2, int $delayMs = 700 ) :array {
		return [
			'subject'              => sanitize_key( $subject ),
			'minimum_input_length' => \max( 1, $minimumInputLength ),
			'delay_ms'             => \max( 0, $delayMs ),
			'action'               => ActionData::Build( InvestigateLookupSelect::class ),
		];
	}

	protected function buildFullLogHrefWithSearch( string $nav, string $subNav, string $search ) :string {
		return URL::Build(
			self::con()->plugin_urls->adminTopNav( $nav, $subNav ),
			[
				'search' => $search,
			]
		);
	}

	/**
	 * @param array<string,mixed> $datatablesInit
	 * @param array<string,mixed> $tableAction
	 * @return InvestigationTableContract
	 */
	protected function buildTableContainerContract(
		string $title,
		string $status,
		string $tableType,
		string $subjectType,
		string $subjectId,
		array $datatablesInit,
		array $tableAction,
		string $fullLogHref
	) :array {
		return $this->normalizeInvestigationTableContract( [
			'title'           => $title,
			'status'          => $status,
			'table_type'      => $tableType,
			'subject_type'    => $subjectType,
			'subject_id'      => $subjectId,
			'datatables_init' => $datatablesInit,
			'table_action'    => $tableAction,
			'full_log_href'   => $fullLogHref,
		] );
	}

	/**
	 * @param array<string,mixed> $datatablesInit
	 * @param array<string,mixed> $scanResultsActionData
	 * @return InvestigationTableContract
	 */
	protected function buildFlatScanResultsTableContract(
		string $title,
		string $status,
		string $tableType,
		string $subjectType,
		string $subjectId,
		array $datatablesInit,
		array $scanResultsActionData,
		string $fullLogHref
	) :array {
		$table = $this->buildTableContainerContract(
			$title,
			$status,
			$tableType,
			$subjectType,
			$subjectId,
			$datatablesInit,
			ActionData::Build( InvestigationTableAction::class ),
			$fullLogHref
		);
		$table[ 'full_log_text' ] = __( 'Full Scan Results', 'wp-simple-firewall' );
		$table[ 'full_log_button_class' ] = 'btn btn-primary btn-sm';
		$table[ 'show_header' ] = false;
		$table[ 'scan_results_action' ] = ActionData::Build( ScanResultsTableAction::class, true, $scanResultsActionData );
		$table[ 'render_item_analysis' ] = ActionData::BuildAjaxRender( Components\Scans\ItemAnalysis\Container::class );
		$table[ 'is_flat' ] = true;

		return $this->normalizeInvestigationTableContract( $table );
	}

	/**
	 * @param InvestigationTableContractInput $table
	 * @return InvestigationTableContract
	 */
	protected function withEmptyStateTableContract( array $table, int $count, string $emptyText, string $emptyStatus = 'info' ) :array {
		if ( $count > 0 ) {
			$table[ 'is_empty' ] = false;
			return $this->normalizeInvestigationTableContract( $table );
		}

		$table[ 'is_empty' ] = true;
		$table[ 'empty_status' ] = $emptyStatus;
		$table[ 'empty_text' ] = $emptyText;
		unset( $table[ 'datatables_init' ], $table[ 'table_action' ], $table[ 'table_type' ], $table[ 'subject_type' ], $table[ 'subject_id' ] );
		return $this->normalizeInvestigationTableContract( $table );
	}

	/**
	 * @param InvestigationTableContractInput $table
	 * @return InvestigationTableContract
	 */
	protected function normalizeInvestigationTableContract( array $table ) :array {
		return \array_merge(
			[
				'full_log_text'         => __( 'Full Log', 'wp-simple-firewall' ),
				'full_log_button_class' => 'btn btn-outline-secondary btn-sm',
				'show_header'           => true,
				'is_flat'               => false,
				'is_empty'              => false,
				'empty_status'          => 'info',
				'empty_text'            => '',
			],
			$table
		);
	}
}
