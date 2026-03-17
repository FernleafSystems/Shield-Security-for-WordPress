<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-type LayerContext array{
 *   path:list<string>,
 *   focus:string,
 *   next_step:string
 * }
 * @phpstan-type BucketSelection array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   item_count:int,
 *   strip_text:string,
 *   strip_badge:string,
 *   context:LayerContext,
 *   context_json:string,
 *   selection_json:string
 * }
 * @phpstan-type GroupSelection array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   item_count:int,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   strip_text:string,
 *   strip_badge:string,
 *   context:LayerContext,
 *   context_json:string,
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

	public function buildStripText( string $label, int $itemCount ) :string {
		return \sprintf(
			_n( '%1$s - %2$s item', '%1$s - %2$s items', $itemCount, 'wp-simple-firewall' ),
			$label,
			$itemCount
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
	 * @param LayerContext $context
	 * @return BucketSelection
	 */
	public function buildBucketSelection(
		string $key,
		string $label,
		string $status,
		int $itemCount,
		array $context
	) :array {
		$selection = [
			'key'         => $key,
			'label'       => $label,
			'status'      => $status,
			'item_count'  => $itemCount,
			'strip_text'  => $this->buildStripText( $label, $itemCount ),
			'strip_badge' => $this->buildItemBadge( $itemCount ),
			'context'     => $context,
		];

		$selection[ 'context_json' ] = $this->encodeJson( $context );
		$selection[ 'selection_json' ] = $this->encodeJson( $selection );

		return $selection;
	}

	/**
	 * @param LayerContext $context
	 * @return GroupSelection
	 */
	public function buildGroupSelection(
		string $key,
		string $label,
		string $status,
		int $itemCount,
		string $detailShell,
		array $context
	) :array {
		$selection = [
			'key'          => $key,
			'label'        => $label,
			'status'       => $status,
			'item_count'   => $itemCount,
			'detail_shell' => $detailShell,
			'strip_text'   => $this->buildStripText( $label, $itemCount ),
			'strip_badge'  => $this->buildItemBadge( $itemCount ),
			'context'      => $context,
		];

		$selection[ 'context_json' ] = $this->encodeJson( $context );
		$selection[ 'selection_json' ] = $this->encodeJson( $selection );

		return $selection;
	}

	private function encodeJson( array $data ) :string {
		return (string)( \json_encode( $data ) ?: '' );
	}
}
