<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type ConfigureComponentContract from ConfigureZoneTilesBuilder
 * @phpstan-import-type DetailActionData from StatusDetailGroupsBuilder
 * @phpstan-import-type DetailGroup from StatusDetailGroupsBuilder
 * @phpstan-import-type DrillLayerHeader from OperatorChromeContract
 * @phpstan-import-type OperatorChromeStep from OperatorChromeContract
 * @phpstan-type ConfigureLandingTile array{
 *   key:string,
 *   panel_target:string,
 *   is_enabled:bool,
 *   is_disabled:bool,
 *   include_in_posture:bool,
 *   label:string,
 *   icon_class:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   stat_line:string,
 *   panel:array{
 *     title:string,
 *     status:string,
 *     status_label:string,
 *     components:list<ConfigureComponentContract>,
 *     detail_groups:list<DetailGroup>
 *   }
 * }
 * @phpstan-type ZoneCard array{
 *   key:string,
 *   label:string,
 *   icon_class:string,
 *   status:string,
 *   status_label:string,
 *   preview_text:string,
 *   selection_json:string,
 *   is_disabled:bool
 * }
 * @phpstan-type ZoneSection array{
 *   key:'critical'|'warning'|'general'|'healthy',
 *   cards:list<ZoneCard>,
 *   collapsible:bool,
 *   disclosure_label:string
 * }
 * @phpstan-type ConfigurePostureSummary array{
 *   status:string,
 *   chip_label:string,
 *   icon_class:string,
 *   eyebrow:string,
 *   summary:string,
 *   meter:array{
 *     percentage:int,
 *     status:string,
 *     aria_label:string,
 *     aria_value_text:string
 *   }
 * }
 * @phpstan-type DrillSelection array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   icon_class:string,
 *   header:DrillLayerHeader
 * }
 * @phpstan-type DiagnosisExpandAction array{
 *   is_expandable:bool,
 *   label:string,
 *   title:string,
 *   data_attributes:DetailActionData
 * }
 * @phpstan-type DiagnosisFinding array{
 *   title:string,
 *   summary:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   explanations:list<string>,
 *   expand_action:DiagnosisExpandAction
 * }
 * @phpstan-type DiagnosisContract array{
 *   zone_key:string,
 *   zone_label:string,
 *   zone_icon_class:string,
 *   zone_status:string,
 *   zone_status_label:string,
 *   preview_text:string,
 *   risk_context:string,
 *   next_move_heading:string,
 *   next_move:string,
 *   problem_rows:list<DiagnosisFinding>,
 *   review_rows:list<DiagnosisFinding>,
 *   healthy_rows:list<DiagnosisFinding>,
 *   healthy_rows_heading:string,
 *   header:DrillLayerHeader,
 *   zone_selection:DrillSelection,
 *   zone_selection_json:string
 * }
 * @phpstan-type ConfigureLandingViewData array{
 *   tiles:list<ConfigureLandingTile>,
 *   tile_lookup:array<string,ConfigureLandingTile>,
 *   diagnoses:array<string,DiagnosisContract>,
 *   sections:list<ZoneSection>,
 *   posture_summary:ConfigurePostureSummary,
 *   root_step:OperatorChromeStep,
 *   root_step_json:string
 * }
 */
final class ConfigureLandingRenderContracts {

	private function __construct() {
	}
}
