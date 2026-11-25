<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	CloakedPluginsQueueIssueProvider,
	ScansVulnerabilitiesBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * How much the Actions Queue holds that someone has ignored. Ignored items count for nothing, so wherever Shield
 * says there's nothing to do, it says how many it's leaving out. These are the items the queue itself lists as
 * ignored, each with its way back.
 */
class ActionsQueueIgnoredCounter {

	use PluginControllerConsumer;

	public function toDos() :int {
		return (int)\array_sum( \array_column( $this->buildMaintenanceStates(), 'ignored_count' ) );
	}

	/**
	 * Abandoned plugins and themes, and cloaked plugins: the findings that are ignored in the queue itself.
	 */
	public function securityIssues() :int {
		$count = 0;
		if ( self::con()->comps->scans->APC()->isEnabled() ) {
			// Only the ignored ones are read, not every vulnerable and abandoned asset.
			$count += \count( $this->buildVulnerabilitiesBuilder()->buildIgnoredAbandonedItems() );
		}
		if ( self::con()->caps->canDetectCloakedPlugins() ) {
			$count += $this->buildCloakedPluginsProvider()->ignoredByUserCount();
		}
		return $count;
	}

	/**
	 * @return array<string,array{ignored_count:int}>
	 */
	protected function buildMaintenanceStates() :array {
		return ( new MaintenanceIssueStateProvider() )->buildStates();
	}

	protected function buildVulnerabilitiesBuilder() :ScansVulnerabilitiesBuilder {
		return new ScansVulnerabilitiesBuilder();
	}

	protected function buildCloakedPluginsProvider() :CloakedPluginsQueueIssueProvider {
		return new CloakedPluginsQueueIssueProvider();
	}
}
