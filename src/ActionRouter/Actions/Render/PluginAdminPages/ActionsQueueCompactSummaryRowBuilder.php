<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type MaintenanceUiAction from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-type CompactSummaryRow array{
 *   icon_class:string,
 *   title:string,
 *   summary:string,
 *   badge_label:string,
 *   is_ignored:bool,
 *   actions:list<MaintenanceUiAction>
 * }
 */
class ActionsQueueCompactSummaryRowBuilder {

	/**
	 * @param list<MaintenanceUiAction> $actions
	 * @return CompactSummaryRow
	 */
	public function build(
		string $iconClass,
		string $title,
		string $summary,
		string $badgeLabel = '',
		bool $isIgnored = false,
		array $actions = []
	) :array {
		return [
			'icon_class'  => $iconClass,
			'title'       => $title,
			'summary'     => $summary,
			'badge_label' => $badgeLabel,
			'is_ignored'  => $isIgnored,
			'actions'     => $actions,
		];
	}
}
