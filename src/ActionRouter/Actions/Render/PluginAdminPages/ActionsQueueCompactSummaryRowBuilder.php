<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type VulnerabilityItem from ScansVulnerabilitiesBuilder
 * @phpstan-type CompactSummaryAction array{
 *   href:string,
 *   is_action:bool,
 *   label:string,
 *   icon:string,
 *   tooltip:string,
 *   target:string,
 *   ajax_action_json:string,
 *   kind?:string,
 *   maintenance_key?:string
 * }
 * @phpstan-type CompactSummaryRow array{
 *   icon_class:string,
 *   title:string,
 *   inline_meta:string,
 *   summary:string,
 *   badge_label:string,
 *   is_ignored:bool,
 *   actions:list<CompactSummaryAction>
 * }
 */
class ActionsQueueCompactSummaryRowBuilder {

	/**
	 * @param list<CompactSummaryAction> $actions
	 * @return CompactSummaryRow
	 */
	public function build(
		string $iconClass,
		string $title,
		string $summary,
		string $badgeLabel = '',
		bool $isIgnored = false,
		array $actions = [],
		string $inlineMeta = ''
	) :array {
		return [
			'icon_class'  => $iconClass,
			'title'       => $title,
			'inline_meta' => $inlineMeta,
			'summary'     => $summary,
			'badge_label' => $badgeLabel,
			'is_ignored'  => $isIgnored,
			'actions'     => $actions,
		];
	}

	/**
	 * A vulnerable or abandoned plugin or theme, listed in the queue itself with its actions.
	 * @phpstan-param VulnerabilityItem $item
	 * @return CompactSummaryRow
	 */
	public function buildForAsset( array $item ) :array {
		$actions = \array_map(
			static fn( array $action ) :array => [
				'href'             => $action[ 'href' ],
				'is_action'        => false,
				'label'            => $action[ 'label' ],
				'icon'             => $action[ 'icon' ] ?? '',
				'tooltip'          => '',
				'target'           => $action[ 'attributes' ][ 'target' ] ?? '',
				'ajax_action_json' => '',
			],
			$item[ 'actions' ]
		);

		$ignoreAction = $item[ 'ignore_action' ] ?? null;
		if ( $ignoreAction !== null ) {
			$actions[] = [
				'href'             => '',
				'is_action'        => true,
				'kind'             => $ignoreAction[ 'kind' ],
				'label'            => $ignoreAction[ 'label' ],
				'icon'             => $ignoreAction[ 'icon' ],
				'tooltip'          => $ignoreAction[ 'tooltip' ],
				'target'           => '',
				'ajax_action_json' => (string)( \json_encode( $ignoreAction[ 'ajax_action' ] ) ?: '' ),
			];
		}

		$isIgnored = $ignoreAction !== null && $ignoreAction[ 'kind' ] === 'unignore';

		return $this->build(
			$item[ 'asset_type' ] === 'plugin' ? 'bi bi-plug' : 'bi bi-brush',
			$item[ 'label' ],
			$item[ 'description' ],
			$isIgnored ? __( 'Ignored', 'wp-simple-firewall' ) : '',
			$isIgnored,
			$actions
		);
	}

	/**
	 * @phpstan-param list<VulnerabilityItem> $items
	 * @return list<CompactSummaryRow>
	 */
	public function buildForAssets( array $items ) :array {
		return \array_map( fn( array $item ) :array => $this->buildForAsset( $item ), $items );
	}
}
