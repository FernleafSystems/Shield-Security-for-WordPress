<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type DrillLayerHeader from OperatorChromeContract
 * @phpstan-import-type OperatorChromeActionInput from OperatorChromeContract
 * @phpstan-import-type OperatorChromeDisplayOptionsInput from OperatorChromeContract
 * @phpstan-type BucketSelection array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   icon_class:string,
 *   item_count:int,
 *   header:DrillLayerHeader,
 *   selection_json:string
 * }
 * @phpstan-type GroupSelection array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   icon_class:string,
 *   item_count:int,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   detail_render_action:array<string,mixed>,
 *   header:DrillLayerHeader,
 *   selection_json:string
 * }
 */
class ActionsQueueDrillDownPresentationBuilder {

	public function buildItemBadge( int $itemCount ) :string {
		return \sprintf(
			_n( '%s item', '%s items', $itemCount, 'wp-simple-firewall' ),
			$itemCount
		);
	}

	public function buildBackLabel( string $label ) :string {
		return \sprintf(
			__( 'Back to %s', 'wp-simple-firewall' ),
			$label
		);
	}

	public function buildBucketFocusText( string $bucketLabel, int $itemCount ) :string {
		return \sprintf(
			_n(
				'%1$s contains %2$s item that still needs attention.',
				'%1$s contains %2$s items that still need attention.',
				$itemCount,
				'wp-simple-firewall'
			),
			$bucketLabel,
			$itemCount
		);
	}

	/**
	 * @return BucketSelection
	 */
	public function buildBucketSelection(
		string $key,
		string $label,
		string $meta,
		string $status,
		string $iconClass,
		int $itemCount,
		string $summary,
		array $actions = []
	) :array {
		$header = $this->buildBucketHeader(
			$label,
			$meta,
			$status,
			$iconClass,
			$itemCount,
			$summary,
			$actions
		);
		$selection = [
			'key'           => $key,
			'label'         => $label,
			'status'        => $status,
			'icon_class'    => $iconClass,
			'item_count'    => $itemCount,
			'header'        => $header,
		];

		$selection[ 'selection_json' ] = OperatorChromeContract::encodeJson( $selection );

		return $selection;
	}

	/**
	 * @return GroupSelection
	 */
	public function buildGroupSelection(
		string $bucketLabel,
		string $key,
		string $label,
		string $status,
		string $iconClass,
		int $itemCount,
		string $detailShell,
		array $detailRenderAction,
		string $summary,
		array $actions = [],
		array $displayOptions = []
	) :array {
		$header = $this->buildGroupHeader(
			$bucketLabel,
			$label,
			$status,
			$iconClass,
			$itemCount,
			$summary,
			$actions,
			$displayOptions
		);
		$selection = [
			'key'          => $key,
			'label'        => $label,
			'status'       => $status,
			'icon_class'   => $iconClass,
			'item_count'   => $itemCount,
			'detail_shell' => $detailShell,
			'detail_render_action' => $detailRenderAction,
			'header'       => $header,
		];

		$selection[ 'selection_json' ] = OperatorChromeContract::encodeJson( $selection );

		return $selection;
	}

	/**
	 * @return DrillLayerHeader
	 */
	public function buildBucketHeader(
		string $label,
		string $meta,
		string $status,
		string $iconClass,
		int $itemCount,
		string $summary,
		array $actions = []
	) :array {
		return OperatorChromeContract::normalizeHeader( [
			'compact_back_label' => $this->buildBackLabel( $label ),
			'active_back_label'  => $this->buildBackLabel( __( 'Actions Queue', 'wp-simple-firewall' ) ),
			'breadcrumb_label'   => $label,
			'title'              => $label,
			'meta'               => $meta,
			'summary'            => $summary,
			'focus'              => $meta,
			'next_step'          => __( 'Choose one grouped finding to review the matching results.', 'wp-simple-firewall' ),
			'icon_class'         => $iconClass,
			'badge'              => $this->buildItemBadge( $itemCount ),
			'badge_status'       => $status,
			'color_key'          => $status,
			'actions'            => $actions,
		] );
	}

	/**
	 * @return DrillLayerHeader
	 */
	public function buildGroupHeader(
		string $bucketLabel,
		string $label,
		string $status,
		string $iconClass,
		int $itemCount,
		string $summary,
		array $actions = [],
		array $displayOptions = []
	) :array {
		return OperatorChromeContract::normalizeHeader( [
			'compact_back_label' => $this->buildBackLabel( $label ),
			'active_back_label'  => $this->buildBackLabel( $bucketLabel ),
			'breadcrumb_label'   => $label,
			'title'              => $label,
			'summary'            => $summary,
			'focus'              => $this->buildItemBadge( $itemCount ),
			'next_step'          => __( 'Review the scoped results and complete the next action.', 'wp-simple-firewall' ),
			'icon_class'         => $iconClass,
			'badge'              => $this->buildItemBadge( $itemCount ),
			'badge_status'       => $status,
			'color_key'          => $status,
			'actions'            => $actions,
			'display_options'    => $displayOptions,
		] );
	}
}
