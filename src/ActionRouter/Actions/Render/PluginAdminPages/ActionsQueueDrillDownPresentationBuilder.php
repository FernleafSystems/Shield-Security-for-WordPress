<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type DrillLayerHeaderInput from PageDrillDownLandingBase
 * @phpstan-type BucketSelection array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   icon_class:string,
 *   item_count:int,
 *   header:DrillLayerHeaderInput,
 *   header_json:string,
 *   selection_json:string
 * }
 * @phpstan-type GroupSelection array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   icon_class:string,
 *   item_count:int,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   header:DrillLayerHeaderInput,
 *   header_json:string,
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
		string $summary
	) :array {
		$header = $this->buildBucketHeader(
			$label,
			$meta,
			$status,
			$iconClass,
			$itemCount,
			$summary
		);
		$selection = [
			'key'           => $key,
			'label'         => $label,
			'status'        => $status,
			'icon_class'    => $iconClass,
			'item_count'    => $itemCount,
			'header'        => $header,
		];

		$selection[ 'header_json' ] = $this->encodeJson( $header );
		$selection[ 'selection_json' ] = $this->encodeJson( $selection );

		return $selection;
	}

	/**
	 * @return BucketSelection
	 */
	public function buildGroupSelection(
		string $bucketLabel,
		string $key,
		string $label,
		string $status,
		string $iconClass,
		int $itemCount,
		string $detailShell,
		string $summary
	) :array {
		$header = $this->buildGroupHeader(
			$bucketLabel,
			$label,
			$status,
			$iconClass,
			$itemCount,
			$summary
		);
		$selection = [
			'key'          => $key,
			'label'        => $label,
			'status'       => $status,
			'icon_class'   => $iconClass,
			'item_count'   => $itemCount,
			'detail_shell' => $detailShell,
			'header'       => $header,
		];

		$selection[ 'header_json' ] = $this->encodeJson( $header );
		$selection[ 'selection_json' ] = $this->encodeJson( $selection );

		return $selection;
	}

	/**
	 * @return DrillLayerHeaderInput
	 */
	public function buildBucketHeader(
		string $label,
		string $meta,
		string $status,
		string $iconClass,
		int $itemCount,
		string $summary
	) :array {
		return [
			'compact_back_label' => $this->buildBackLabel( $label ),
			'active_back_label'  => $this->buildBackLabel( __( 'Actions Queue', 'wp-simple-firewall' ) ),
			'title'              => $label,
			'meta'               => $meta,
			'summary'            => $summary,
			'icon_class'         => $iconClass,
			'badge'              => $this->buildItemBadge( $itemCount ),
			'badge_status'       => $status,
		];
	}

	/**
	 * @return DrillLayerHeaderInput
	 */
	public function buildGroupHeader(
		string $bucketLabel,
		string $label,
		string $status,
		string $iconClass,
		int $itemCount,
		string $summary
	) :array {
		return [
			'compact_back_label' => $this->buildBackLabel( $label ),
			'active_back_label'  => $this->buildBackLabel( $bucketLabel ),
			'title'              => $label,
			'summary'            => $summary,
			'icon_class'         => $iconClass,
			'badge'              => $this->buildItemBadge( $itemCount ),
			'badge_status'       => $status,
		];
	}

	private function encodeJson( array $data ) :string {
		return (string)( \json_encode( $data ) ?: '' );
	}
}
