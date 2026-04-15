<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\InvestigateLookupSelect,
	Actions\InvestigationTableAction
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
 * @phpstan-type LookupShortcutContract array{
 *   key:string,
 *   href:string,
 *   label:string,
 *   action_type:string,
 *   icon_class:string
 * }
 * @phpstan-type LookupDisplayContract array{
 *   show_subject_header:bool,
 *   show_lookup_with_subject:bool,
 *   change_label:string
 * }
 * @phpstan-type SubjectHeaderContract array{
 *   title:string,
 *   meta:string,
 *   context_step_json:string
 * }
 * @phpstan-type InvestigationTableContract array{
 *   title:string,
 *   status:string,
 *   table_type?:string,
 *   subject_type?:string,
 *   subject_id?:string,
 *   datatables_init_attr?:string,
 *   table_action_attr?:string,
 *   full_log_href?:string,
 *   full_log_text:string,
 *   full_log_button_class:string,
 *   show_header:bool,
 *   is_flat:bool,
 *   is_empty:bool,
 *   empty_status:string,
 *   empty_text:string
 * }
 * @phpstan-type InvestigationTableContractInput array{
 *   title:string,
 *   status:string,
 *   table_type?:string,
 *   subject_type?:string,
 *   subject_id?:string,
 *   datatables_init?:array<string,mixed>,
 *   table_action?:array<string,mixed>,
 *   datatables_init_attr?:string,
 *   table_action_attr?:string,
 *   full_log_href?:string,
 *   full_log_text?:string,
 *   full_log_button_class?:string,
 *   show_header?:bool,
 *   is_flat?:bool,
 *   is_empty?:bool,
 *   empty_status?:string,
 *   empty_text?:string
 * }
 * @phpstan-type InvestigationRenderableTableContract array{
 *   title:string,
 *   status:string,
 *   table_type:string,
 *   subject_type:string,
 *   subject_id:string,
 *   datatables_init_attr:string,
 *   table_action_attr:string,
 *   full_log_href?:string,
 *   full_log_text:string,
 *   full_log_button_class:string,
 *   show_header:bool,
 *   is_flat:bool,
 *   is_empty:false,
 *   empty_status:string,
 *   empty_text:string
 * }|array{
 *   title:string,
 *   status:string,
 *   full_log_href?:string,
 *   full_log_text:string,
 *   full_log_button_class:string,
 *   show_header:bool,
 *   is_flat:bool,
 *   is_empty:true,
 *   empty_status:string,
 *   empty_text:string
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

	protected function buildLookupAjaxAttrValue( array $lookupAjax ) :string {
		return $this->buildJsonAttrValue( $lookupAjax );
	}

	/**
	 * @return SubjectHeaderContract
	 */
	protected function buildSubjectHeaderContract( string $title, string $meta = '', string $contextStepJson = '' ) :array {
		return [
			'title'             => $title,
			'meta'              => $meta,
			'context_step_json' => $contextStepJson,
		];
	}

	/**
	 * @return LookupShortcutContract
	 */
	protected function buildLookupShortcutContract(
		string $key,
		string $href,
		string $label,
		string $actionType = 'navigate',
		string $iconClass = ''
	) :array {
		return [
			'key'         => sanitize_key( $key ),
			'href'        => $href,
			'label'       => $label,
			'action_type' => sanitize_key( $actionType ),
			'icon_class'  => \trim( $iconClass ),
		];
	}

	/**
	 * @param array<string,mixed> $display
	 * @return LookupDisplayContract
	 */
	protected function normalizeLookupDisplayContract( array $display = [] ) :array {
		return \array_merge(
			[
				'show_subject_header'      => true,
				'show_lookup_with_subject' => false,
				'change_label'             => '',
			],
			$display
		);
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
	 * @return InvestigationRenderableTableContract
	 */
	protected function buildTableContainerContract(
		string $title,
		string $status,
		string $tableType,
		string $subjectType,
		string $subjectId,
		array $datatablesInit,
		array $tableAction,
		string $fullLogHref = ''
	) :array {
		$table = [
			'title'               => $title,
			'status'              => $status,
			'table_type'          => $tableType,
			'subject_type'        => $subjectType,
			'subject_id'          => $subjectId,
			'datatables_init_attr' => $this->buildJsonAttrValue( $datatablesInit ),
			'table_action_attr'   => $this->buildJsonAttrValue( $tableAction ),
		];
		if ( $fullLogHref !== '' ) {
			$table[ 'full_log_href' ] = $fullLogHref;
		}

		return $this->normalizeInvestigationTableContract( $table );
	}

	/**
	 * @param InvestigationTableContractInput $table
	 * @return InvestigationRenderableTableContract
	 */
	protected function withEmptyStateTableContract( array $table, int $count, string $emptyText, string $emptyStatus = 'info' ) :array {
		if ( $count > 0 ) {
			$table[ 'is_empty' ] = false;
			return $this->normalizeInvestigationTableContract( $table );
		}

		$table[ 'is_empty' ] = true;
		$table[ 'empty_status' ] = $emptyStatus;
		$table[ 'empty_text' ] = $emptyText;
		unset(
			$table[ 'datatables_init' ],
			$table[ 'table_action' ],
			$table[ 'table_type' ],
			$table[ 'subject_type' ],
			$table[ 'subject_id' ],
			$table[ 'datatables_init_attr' ],
			$table[ 'table_action_attr' ],
		);
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

	/**
	 * @param array<string,mixed> $data
	 */
	private function buildJsonAttrValue( array $data ) :string {
		return empty( $data ) ? '' : ( \is_string( $encoded = \json_encode( $data ) ) ? $encoded : '' );
	}
}
