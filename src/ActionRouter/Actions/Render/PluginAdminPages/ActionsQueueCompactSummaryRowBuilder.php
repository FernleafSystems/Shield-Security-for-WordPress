<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-type CompactSummaryAction array{
 *   href:string,
 *   is_action:bool,
 *   label:string,
 *   icon:string,
 *   tooltip:string,
 *   target:string,
 *   ajax_action_json:string
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
}
