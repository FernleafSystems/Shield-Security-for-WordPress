<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakReason,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\ActionsQueueItemIcons;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-type CloakedPluginDetailAction array{
 *   href:string,
 *   label:string,
 *   type:'deactivate'|'navigate',
 *   icon:string,
 *   is_action:false,
 *   tooltip:string,
 *   attributes:array<string,string>
 * }
 * @phpstan-type CloakedPluginDetailRow array{
 *   title:string,
 *   description:string,
 *   status:'critical',
 *   status_icon:null,
 *   status_label:string,
 *   count_badge:null,
 *   badge_status:'critical',
 *   expandable:false,
 *   expand_target:'',
 *   expand_cta_label:'',
 *   expand_accessible_label:'',
 *   expand_title:'',
 *   expansion:array{},
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
		$count = \count( $this->findings() );
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
				'ignored_count'      => 0,
				'severity'           => 'critical',
				'href'               => self::con()->plugin_urls->actionsQueueScans(),
				'action'             => __( 'Review', 'wp-simple-firewall' ),
				'target'             => '',
				'supports_sub_items' => false,
			],
		];
	}

	/**
	 * @return list<AssessmentRow>
	 */
	public function assessmentRows() :array {
		$count = \count( $this->findings() );
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
			],
		];
	}

	/**
	 * @return CloakedPluginsRailPane
	 */
	public function railPaneData() :array {
		$findings = $this->findings();
		$count = \count( $findings );

		return [
			'key'                    => self::KEY,
			'label'                  => $this->label(),
			'icon_class'             => $this->iconClass(),
			'count_items'            => $count,
			'status'                 => $count > 0 ? 'critical' : 'good',
			'items'                  => \array_map(
				fn( CloakedPluginFinding $finding ) :array => $this->detailRow( $finding ),
				$findings
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
	protected function findings() :array {
		return self::con()->comps->hidden_plugins->currentFindings();
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
	private function detailRow( CloakedPluginFinding $finding ) :array {
		return [
			'title'                   => $this->findingTitle( $finding ),
			'description'             => $this->findingDescription( $finding ),
			'status'                  => 'critical',
			'status_icon'             => null,
			'status_label'            => $this->standardStatusLabel( 'critical' ),
			'count_badge'             => null,
			'badge_status'            => 'critical',
			'expandable'              => false,
			'expand_target'           => '',
			'expand_cta_label'        => '',
			'expand_accessible_label' => '',
			'expand_title'            => '',
			'expansion'               => [],
			'explanations'            => $this->findingExplanations( $finding ),
			'show_gear'               => false,
			'actions'                 => $this->findingActions( $finding ),
			'attributes'              => [],
			'section_label'           => PluginType::label( $finding->entry->type ),
		];
	}

	private function findingTitle( CloakedPluginFinding $finding ) :string {
		return \trim( $finding->entry->name ) !== '' ? $finding->entry->name : $finding->entry->file;
	}

	private function findingDescription( CloakedPluginFinding $finding ) :string {
		return \sprintf(
			__( '%s is present on disk but cloaked from WordPress plugin lists.', 'wp-simple-firewall' ),
			PluginType::label( $finding->entry->type )
		);
	}

	/**
	 * @return list<string>
	 */
	private function findingExplanations( CloakedPluginFinding $finding ) :array {
		return [
			\sprintf( __( 'File: %s', 'wp-simple-firewall' ), $finding->entry->file ),
			\sprintf( __( 'Path: %s', 'wp-simple-firewall' ), $finding->entry->path ),
			\sprintf( __( 'Status: %s', 'wp-simple-firewall' ), $this->statusLabel( $finding ) ),
			\sprintf(
				__( 'Cloaked By: %s', 'wp-simple-firewall' ),
				\implode( ', ', \array_map(
					static fn( string $reason ) :string => CloakReason::label( $reason ),
					$finding->cloakReasons
				) )
			),
			$this->nextStep( $finding ),
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
			default:
				return __( 'Inactive', 'wp-simple-firewall' );
		}
	}

	private function nextStep( CloakedPluginFinding $finding ) :string {
		if ( $finding->entry->type === PluginType::MustUse ) {
			return __( 'Next Step: Remove this must-use plugin file manually if it should not be installed.', 'wp-simple-firewall' );
		}

		return ( $finding->active || $finding->networkActive )
			? __( 'Next Step: Deactivate this plugin, then remove the file if it should not be installed.', 'wp-simple-firewall' )
			: __( 'Next Step: Remove this plugin file manually if it should not be installed.', 'wp-simple-firewall' );
	}

	/**
	 * @return list<CloakedPluginDetailAction>
	 */
	private function findingActions( CloakedPluginFinding $finding ) :array {
		if ( $finding->entry->type === PluginType::MustUse ) {
			return [];
		}

		if ( $finding->active || $finding->networkActive ) {
			return $this->actionList( $this->deactivateUrl( $finding->entry->file ), __( 'Deactivate Plugin', 'wp-simple-firewall' ), 'deactivate', 'bi bi-power' );
		}

		return $this->actionList( $this->pluginsSearchUrl( $finding->entry->file ), __( 'Manage Plugins', 'wp-simple-firewall' ), 'navigate', 'bi bi-arrow-right-circle-fill' );
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

	protected function deactivateUrl( string $pluginFile ) :string {
		return Services::WpPlugins()->getUrl_Deactivate( $pluginFile );
	}

	protected function pluginsSearchUrl( string $pluginFile ) :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl_Plugins(), [
			's' => $pluginFile,
		] );
	}
}
