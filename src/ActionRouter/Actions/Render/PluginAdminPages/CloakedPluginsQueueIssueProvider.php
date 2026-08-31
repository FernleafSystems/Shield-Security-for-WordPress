<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	CloakedPluginIgnore,
	CloakedPluginUnignore
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakedPluginState,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\ActionsQueueItemIcons;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type CloakedPluginFindingState from CloakedPluginState
 * @phpstan-type CloakedPluginDetailAction array{
 *   href:string,
 *   label:string,
 *   type:'deactivate'|'navigate',
 *   icon:string,
 *   is_action:bool,
 *   tooltip:string,
 *   attributes:array<string,string>
 * }
 * @phpstan-type CloakedPluginDetailItem array{
 *   label:string,
 *   value:string,
 *   style:'text'|'code'
 * }
 * @phpstan-type CloakedPluginDetailRow array{
 *   title:string,
 *   description:string,
 *   status:'critical'|'good',
 *   status_icon:null,
 *   status_label:string,
 *   count_badge:null,
 *   badge_status:'critical'|'good',
 *   expandable:false,
 *   expand_target:'',
 *   expand_cta_label:'',
 *   expand_accessible_label:'',
 *   expand_title:'',
 *   expansion:array{},
 *   detail_items:list<CloakedPluginDetailItem>,
 *   explanations:list<string>,
 *   show_gear:false,
 *   actions:list<CloakedPluginDetailAction>,
 *   attributes:array<string,string>,
 *   section_label:string
 * }
 * @phpstan-type CloakedPluginsRailPane array{
 *   key:'hidden_plugins',
 *   label:string,
 *   status:'critical'|'good',
 *   icon_class:string,
 *   count_items:int,
 *   items:list<CloakedPluginDetailRow>,
 *   is_loaded:true,
 *   is_disabled:false,
 *   disabled_message:'',
 *   disabled_status:'neutral',
 *   disabled_actions:array{},
 *   render_action:array{},
 *   show_count_placeholder:false,
 *   pane_id:'actions-queue-cloaked-plugins'
 * }
 */
class CloakedPluginsQueueIssueProvider implements ActionsQueueSecurityCheckProvider {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	public const KEY = 'hidden_plugins';
	public const SOURCE = 'security_check';

	/**
	 * @return list<AttentionItem>
	 */
	public function attentionItems() :array {
		$activeFindings = $this->activeFindings();
		$count = \count( $activeFindings );
		if ( $count < 1 ) {
			return [];
		}

		return [
			[
				'key'                => self::KEY,
				'zone'               => 'scans',
				'source'             => self::SOURCE,
				'label'              => $this->label(),
				'description'        => $this->descriptionForCount( $count ),
				'count'              => $count,
				'ignored_count'      => \count( $this->ignoredFindings() ),
				'severity'           => 'critical',
				'href'               => self::con()->plugin_urls->actionsQueueScans(),
				'action'             => __( 'Review', 'wp-simple-firewall' ),
				'target'             => '',
				'supports_sub_items' => true,
			],
		];
	}

	/**
	 * @return list<AssessmentRow>
	 */
	public function assessmentRows() :array {
		$count = \count( $this->activeFindings() );
		$status = $count > 0 ? 'critical' : 'good';

		return [
			[
				'key'               => self::KEY,
				'label'             => $this->label(),
				'description'       => $this->descriptionForCount( $count ),
				'drill_bucket'      => 'critical',
				'item_icon_class'   => $this->iconClass(),
				'status'            => $status,
				'status_label'      => $this->standardStatusLabel( $status ),
				'status_icon_class' => $this->standardStatusIconClass( $status ),
				'has_useful_detail' => $count > 0 || \count( $this->ignoredFindings() ) > 0,
			],
		];
	}

	/**
	 * @return CloakedPluginsRailPane
	 */
	public function railPaneData() :array {
		$activeFindings = $this->activeFindings();
		$ignoredFindings = $this->ignoredFindings();
		$count = \count( $activeFindings );

		return [
			'key'                    => self::KEY,
			'label'                  => $this->label(),
			'icon_class'             => $this->iconClass(),
			'count_items'            => $count,
			'status'                 => $count > 0 ? 'critical' : 'good',
			'items'                  => \array_merge(
				\array_map(
					fn( CloakedPluginFinding $finding ) :array => $this->detailRow( $finding, false ),
					$activeFindings
				),
				\array_map(
					fn( CloakedPluginFinding $finding ) :array => $this->detailRow( $finding, true ),
					$ignoredFindings
				)
			),
			'is_loaded'              => true,
			'is_disabled'            => false,
			'disabled_message'       => '',
			'disabled_status'        => 'neutral',
			'disabled_actions'       => [],
			'render_action'          => [],
			'show_count_placeholder' => false,
			'pane_id'                => 'actions-queue-cloaked-plugins',
		];
	}

	/**
	 * @return list<CloakedPluginFinding>
	 */
	protected function activeFindings() :array {
		return $this->state()[ 'active' ];
	}

	/**
	 * @return list<CloakedPluginFinding>
	 */
	protected function ignoredFindings() :array {
		return $this->state()[ 'ignored' ];
	}

	/**
	 * @return CloakedPluginFindingState
	 */
	protected function state() :array {
		return self::con()->comps->hidden_plugins->currentState();
	}

	private function label() :string {
		return __( 'Cloaked Plugins', 'wp-simple-firewall' );
	}

	private function iconClass() :string {
		return ( new ActionsQueueItemIcons() )->iconClassForKey( self::KEY );
	}

	private function descriptionForCount( int $count ) :string {
		return $count > 0
			? \sprintf(
				_n( '%s cloaked plugin detected.', '%s cloaked plugins detected.', $count, 'wp-simple-firewall' ),
				$count
			)
			: __( 'No cloaked plugins are currently detected.', 'wp-simple-firewall' );
	}

	/**
	 * @return CloakedPluginDetailRow
	 */
	private function detailRow( CloakedPluginFinding $finding, bool $isIgnored ) :array {
		$status = $isIgnored ? 'good' : 'critical';

		return [
			'title'                   => $this->findingTitle( $finding ),
			'description'             => $this->findingDescription( $finding, $isIgnored ),
			'status'                  => $status,
			'status_icon'             => null,
			'status_label'            => $isIgnored ? __( 'Ignored', 'wp-simple-firewall' ) : $this->standardStatusLabel( $status ),
			'count_badge'             => null,
			'badge_status'            => $status,
			'expandable'              => false,
			'expand_target'           => '',
			'expand_cta_label'        => '',
			'expand_accessible_label' => '',
			'expand_title'            => '',
			'expansion'               => [],
			'detail_items'            => $this->findingDetailItems( $finding, $isIgnored ),
			'explanations'            => [],
			'show_gear'               => false,
			'actions'                 => $this->findingActions( $finding, $isIgnored ),
			'attributes'              => [],
			'section_label'           => PluginType::label( $finding->entry->type ),
		];
	}

	private function findingTitle( CloakedPluginFinding $finding ) :string {
		return \trim( $finding->entry->name ) !== '' ? $finding->entry->name : $finding->entry->file;
	}

	private function findingDescription( CloakedPluginFinding $finding, bool $isIgnored ) :string {
		$description = \sprintf(
			__( '%s exists on disk, but WordPress is not listing it where expected.', 'wp-simple-firewall' ),
			PluginType::label( $finding->entry->type )
		);

		return $isIgnored
			? $description.' '.__( 'This result is currently ignored.', 'wp-simple-firewall' )
			: $description;
	}

	/**
	 * @return list<CloakedPluginDetailItem>
	 */
	private function findingDetailItems( CloakedPluginFinding $finding, bool $isIgnored ) :array {
		$items = [
			$this->detailItem( __( 'File', 'wp-simple-firewall' ), $finding->entry->file, 'code' ),
			$this->detailItem( __( 'Path', 'wp-simple-firewall' ), $finding->relativePath(), 'code' ),
			$this->detailItem( __( 'Status', 'wp-simple-firewall' ), $this->statusLabel( $finding ) ),
			$this->detailItem( __( 'Reason', 'wp-simple-firewall' ), $finding->cloakReasonSummary() ),
		];

		if ( !$isIgnored ) {
			$items[] = $this->detailItem( __( 'Recommended action', 'wp-simple-firewall' ), $this->recommendedAction( $finding ) );
		}

		return $items;
	}

	/**
	 * @param 'text'|'code' $style
	 * @return CloakedPluginDetailItem
	 */
	private function detailItem( string $label, string $value, string $style = 'text' ) :array {
		return [
			'label' => $label,
			'value' => $value,
			'style' => $style,
		];
	}

	private function statusLabel( CloakedPluginFinding $finding ) :string {
		switch ( $finding->status() ) {
			case 'must-use':
				return __( 'Must-Use', 'wp-simple-firewall' );
			case 'network-active':
				return __( 'Network Active', 'wp-simple-firewall' );
			case 'active':
				return __( 'Active', 'wp-simple-firewall' );
			case 'inactive':
				return __( 'Inactive', 'wp-simple-firewall' );
		}
	}

	private function recommendedAction( CloakedPluginFinding $finding ) :string {
		if ( $finding->entry->type === PluginType::MustUse ) {
			return __( 'Remove this must-use plugin file if it should not be installed.', 'wp-simple-firewall' );
		}

		return ( $finding->active || $finding->networkActive )
			? __( 'Deactivate this plugin, then remove the file if it should not be installed.', 'wp-simple-firewall' )
			: __( 'Remove this plugin file if it should not be installed.', 'wp-simple-firewall' );
	}

	/**
	 * @return list<CloakedPluginDetailAction>
	 */
	private function findingActions( CloakedPluginFinding $finding, bool $isIgnored ) :array {
		if ( $isIgnored ) {
			if ( $this->isShieldMuLoaderFinding( $finding ) ) {
				return [];
			}
			return [ $this->ignoreToggleAction( $finding, true ) ];
		}

		$actions = [];
		if ( $this->isShieldMuLoaderFinding( $finding ) ) {
			return $actions;
		}

		if ( $finding->entry->type !== PluginType::MustUse ) {
			if ( $finding->active || $finding->networkActive ) {
				$actions = $this->actionList( $this->deactivateUrl( $finding->entry->file ), __( 'Deactivate Plugin', 'wp-simple-firewall' ), 'deactivate', 'bi bi-power' );
			}
			else {
				$actions = $this->actionList( $this->pluginsSearchUrl( $finding->entry->file ), __( 'Manage Plugins', 'wp-simple-firewall' ), 'navigate', 'bi bi-arrow-right-circle-fill' );
			}
		}

		$actions[] = $this->ignoreToggleAction( $finding, false );
		return $actions;
	}

	/**
	 * @param 'deactivate'|'navigate' $type
	 * @return list<CloakedPluginDetailAction>
	 */
	private function actionList( string $href, string $label, string $type, string $icon ) :array {
		if ( \trim( $href ) === '' || \trim( $label ) === '' ) {
			return [];
		}

		return [
			[
				'href'       => $href,
				'label'      => $label,
				'type'       => $type,
				'icon'       => $icon,
				'is_action'  => false,
				'tooltip'    => '',
				'attributes' => [],
			],
		];
	}

	/**
	 * @return CloakedPluginDetailAction
	 */
	private function ignoreToggleAction( CloakedPluginFinding $finding, bool $isIgnored ) :array {
		$actionClass = $isIgnored ? CloakedPluginUnignore::class : CloakedPluginIgnore::class;
		$label = $isIgnored ? __( 'Stop Ignoring', 'wp-simple-firewall' ) : __( 'Ignore', 'wp-simple-firewall' );
		$actionData = ActionData::Build( $actionClass, true, [
			'finding_id' => $finding->identityKey(),
		] );

		return [
			'href'       => '',
			'label'      => $label,
			'type'       => 'navigate',
			'icon'       => $isIgnored ? 'bi bi-eye-fill' : 'bi bi-eye-slash-fill',
			'is_action'  => true,
			'tooltip'    => $isIgnored
				? __( 'Stop ignoring this cloaked plugin result.', 'wp-simple-firewall' )
				: __( 'Ignore this cloaked plugin result.', 'wp-simple-firewall' ),
			'attributes' => [
				'data-operator-context-action-ajax'       => '1',
				'data-operator-context-action-json'       => OperatorChromeContract::encodeJson( $actionData ),
				'data-operator-context-action-processing' => $isIgnored
					? __( 'Stopping ignore...', 'wp-simple-firewall' )
					: __( 'Ignoring cloaked plugin...', 'wp-simple-firewall' ),
			],
		];
	}

	protected function deactivateUrl( string $pluginFile ) :string {
		return Services::WpPlugins()->getUrl_Deactivate( $pluginFile );
	}

	protected function pluginsSearchUrl( string $pluginFile ) :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins(), [
			's' => $pluginFile,
		] );
	}

	protected function isShieldMuLoaderFinding( CloakedPluginFinding $finding ) :bool {
		return ( new CloakedPluginState() )->isShieldMuLoader( $finding );
	}
}
