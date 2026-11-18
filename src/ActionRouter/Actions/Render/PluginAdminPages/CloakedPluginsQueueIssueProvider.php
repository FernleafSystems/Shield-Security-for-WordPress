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
 * @phpstan-import-type CloakedPluginIgnoreRecord from CloakedPluginState
 * @phpstan-import-type CompactSummaryAction from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-import-type CompactSummaryDetailItem from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-import-type CompactSummaryFoldedDetails from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-import-type CompactSummaryRow from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-type CloakedPluginQueueRows array{
 *   active:list<CompactSummaryRow>,
 *   ignored:list<CompactSummaryRow>
 * }
 */
class CloakedPluginsQueueIssueProvider implements ActionsQueueSecurityCheckProvider {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	public const KEY = 'hidden_plugins';
	public const SOURCE = 'security_check';

	/**
	 * @var array<string,CloakedPluginIgnoreRecord>|null
	 */
	private ?array $loadedIgnoreRecords = null;

	/**
	 * @return list<AttentionItem>
	 */
	public function attentionItems() :array {
		if ( !self::con()->caps->canDetectCloakedPlugins() ) {
			return [];
		}
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
		if ( !self::con()->caps->canDetectCloakedPlugins() ) {
			return [];
		}
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
			],
		];
	}

	/**
	 * Each cloaked plugin as a row of its own in the Actions Queue.
	 * @return CloakedPluginQueueRows
	 */
	public function queueRows() :array {
		return [
			'active'  => \array_map(
				fn( CloakedPluginFinding $finding ) :array => $this->queueRow( $finding, false ),
				$this->activeFindings()
			),
			'ignored' => \array_map(
				fn( CloakedPluginFinding $finding ) :array => $this->queueRow( $finding, true ),
				$this->ignoredFindings()
			),
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

	/**
	 * @return array<string,CloakedPluginIgnoreRecord>
	 */
	protected function ignoreRecords() :array {
		return ( new CloakedPluginState() )->ignoreRecords();
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
			: __( 'No cloaked plugins detected.', 'wp-simple-firewall' );
	}

	/**
	 * @return CompactSummaryRow
	 */
	private function queueRow( CloakedPluginFinding $finding, bool $isIgnored ) :array {
		return ( new ActionsQueueCompactSummaryRowBuilder() )->build(
			'bi bi-plug',
			$this->findingTitle( $finding ),
			$finding->cloakReasonSummary(),
			$isIgnored ? __( 'Ignored', 'wp-simple-firewall' ) : '',
			$isIgnored,
			$this->findingActions( $finding, $isIgnored ),
			'',
			$this->findingDetails( $finding, $isIgnored )
		);
	}

	private function findingTitle( CloakedPluginFinding $finding ) :string {
		return \trim( $finding->entry->name ) !== '' ? $finding->entry->name : $finding->entry->file;
	}

	private function findingDescription( CloakedPluginFinding $finding ) :string {
		return \sprintf(
			__( '%s exists on disk, but WordPress is not listing it where expected.', 'wp-simple-firewall' ),
			PluginType::label( $finding->entry->type )
		);
	}

	/**
	 * @return CompactSummaryFoldedDetails
	 */
	private function findingDetails( CloakedPluginFinding $finding, bool $isIgnored ) :array {
		$items = [
			$this->detailItem( __( 'Finding', 'wp-simple-firewall' ), $this->findingDescription( $finding ) ),
			$this->detailItem( __( 'Path', 'wp-simple-firewall' ), $finding->relativePath(), 'code' ),
			$this->detailItem( __( 'Status', 'wp-simple-firewall' ), $this->statusLabel( $finding ) ),
		];

		if ( $isIgnored ) {
			$items = \array_merge( $items, $this->ignoreDetails( $finding ) );
		}
		else {
			$items[] = $this->detailItem( __( 'Recommended action', 'wp-simple-firewall' ), $this->recommendedAction( $finding ) );
		}

		return [
			'label' => __( 'Details', 'wp-simple-firewall' ),
			'items' => $items,
		];
	}

	/**
	 * How, and since when, the user ignored it. Shield's own loader, ignored automatically, has neither.
	 * @return list<CompactSummaryDetailItem>
	 */
	private function ignoreDetails( CloakedPluginFinding $finding ) :array {
		$this->loadedIgnoreRecords ??= $this->ignoreRecords();
		$record = $this->loadedIgnoreRecords[ $finding->identityKey() ] ?? null;
		if ( $record === null ) {
			return [];
		}

		$items = [
			$this->detailItem(
				__( 'Ignored', 'wp-simple-firewall' ),
				$record[ 'mode' ] === CloakedPluginState::IGNORE_MODE_CONTENTS
					? __( 'Until its main file changes', 'wp-simple-firewall' )
					: __( 'Always, whatever its file contains', 'wp-simple-firewall' )
			),
		];
		if ( $record[ 'ignored_at' ] > 0 ) {
			$items[] = $this->detailItem(
				__( 'Ignored on', 'wp-simple-firewall' ),
				(string)Services::WpGeneral()->getTimeStringForDisplay( $record[ 'ignored_at' ] )
			);
		}
		return $items;
	}

	/**
	 * @param 'text'|'code' $style
	 * @return CompactSummaryDetailItem
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
	 * @return list<CompactSummaryAction>
	 */
	private function findingActions( CloakedPluginFinding $finding, bool $isIgnored ) :array {
		if ( $this->isShieldMuLoaderFinding( $finding ) ) {
			return [];
		}
		if ( $isIgnored ) {
			return [ $this->unignoreAction( $finding ) ];
		}

		$actions = [];
		if ( $finding->entry->type !== PluginType::MustUse ) {
			if ( $finding->active || $finding->networkActive ) {
				$actions = $this->linkActions( $this->deactivateUrl( $finding->entry->file ), __( 'Deactivate Plugin', 'wp-simple-firewall' ), 'bi bi-power' );
			}
			else {
				$actions = $this->linkActions( $this->pluginsSearchUrl( $finding->entry->file ), __( 'Manage Plugins', 'wp-simple-firewall' ), 'bi bi-arrow-right' );
			}
		}

		// "Ignore all" takes only the "until it changes" ignores (kind 'ignore'). A file that can't be read can't be
		// held to its contents, so it can only be ignored wherever it is.
		$contentsHash = $finding->contentsHash();
		if ( $contentsHash !== '' ) {
			$actions[] = $this->ignoreAction( $finding, CloakedPluginState::IGNORE_MODE_CONTENTS, $contentsHash );
		}
		$actions[] = $this->ignoreAction( $finding, CloakedPluginState::IGNORE_MODE_PATH, '' );
		return $actions;
	}

	/**
	 * @return list<CompactSummaryAction>
	 */
	private function linkActions( string $href, string $label, string $icon ) :array {
		if ( \trim( $href ) === '' ) {
			return [];
		}

		return [
			[
				'href'             => $href,
				'is_action'        => false,
				'label'            => $label,
				'icon'             => $icon,
				'tooltip'          => '',
				'target'           => '',
				'ajax_action_json' => '',
				'kind'             => '',
				'maintenance_key'  => '',
			],
		];
	}

	/**
	 * @phpstan-param value-of<CloakedPluginState::IGNORE_MODES> $mode
	 * @return CompactSummaryAction
	 */
	private function ignoreAction( CloakedPluginFinding $finding, string $mode, string $contentsHash ) :array {
		$isUntilChanged = $mode === CloakedPluginState::IGNORE_MODE_CONTENTS;
		$data = [
			'identifier'  => $finding->identityKey(),
			'ignore_mode' => $mode,
		];
		if ( $isUntilChanged ) {
			$data[ 'contents_hash' ] = $contentsHash;
		}

		return [
			'href'             => '',
			'is_action'        => true,
			'kind'             => $isUntilChanged ? 'ignore' : '',
			'maintenance_key'  => self::KEY,
			'label'            => $isUntilChanged ? __( 'Ignore', 'wp-simple-firewall' ) : __( 'Always ignore', 'wp-simple-firewall' ),
			'icon'             => 'bi bi-eye-slash-fill',
			'tooltip'          => $isUntilChanged
				? __( 'Ignore this cloaked plugin until its main file changes.', 'wp-simple-firewall' )
				: __( 'Ignore this cloaked plugin at this location, whatever its file contains.', 'wp-simple-firewall' ),
			'target'           => '',
			'ajax_action_json' => OperatorChromeContract::encodeJson( ActionData::Build( CloakedPluginIgnore::class, true, $data ) ),
		];
	}

	/**
	 * @return CompactSummaryAction
	 */
	private function unignoreAction( CloakedPluginFinding $finding ) :array {
		return [
			'href'             => '',
			'is_action'        => true,
			'kind'             => 'unignore',
			'maintenance_key'  => self::KEY,
			'label'            => __( 'Stop ignoring', 'wp-simple-firewall' ),
			'icon'             => 'bi bi-eye-fill',
			'tooltip'          => __( 'Stop ignoring this cloaked plugin result.', 'wp-simple-firewall' ),
			'target'           => '',
			'ajax_action_json' => OperatorChromeContract::encodeJson( ActionData::Build( CloakedPluginUnignore::class, true, [
				'identifier' => $finding->identityKey(),
			] ) ),
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
