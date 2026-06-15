<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

class PluginVisibilityComparator {

	/**
	 * @param list<PluginEntry> $entries
	 * @return list<HiddenPluginFinding>
	 */
	public function compare( array $entries, AdminPluginVisibilitySnapshot $visibility ) :array {
		$findings = [];
		$detectedAt = \time();

		foreach ( $entries as $entry ) {
			$reasons = $entry->type === PluginType::MustUse ?
				$this->mustUseHiddenReasons( $entry, $visibility )
				: $this->standardHiddenReasons( $entry, $visibility );

			if ( !empty( $reasons ) ) {
				$findings[] = new HiddenPluginFinding(
					$entry,
					$reasons,
					$entry->type === PluginType::MustUse || $visibility->isActive( $entry->file ),
					$entry->type === PluginType::MustUse || $visibility->isNetworkActive( $entry->file ),
					$detectedAt
				);
			}
		}

		return $findings;
	}

	/**
	 * @phpstan-return list<value-of<HiddenReason::ALL>>
	 */
	private function standardHiddenReasons( PluginEntry $entry, AdminPluginVisibilitySnapshot $visibility ) :array {
		$reasons = [];

		if ( !isset( $visibility->wpDiscoveredPlugins[ $entry->file ] ) ) {
			$reasons[] = HiddenReason::WpDiscoveryCacheGap;
		}
		elseif ( !isset( $visibility->adminAllPlugins[ $entry->file ] ) ) {
			$reasons[] = HiddenReason::AllPlugins;
		}

		if ( isset( $visibility->adminAllPlugins[ $entry->file ] ) && !$visibility->isVisibleInFinalList( $entry ) ) {
			$reasons[] = HiddenReason::PluginsList;
		}

		return $reasons;
	}

	/**
	 * @phpstan-return list<value-of<HiddenReason::ALL>>
	 */
	private function mustUseHiddenReasons( PluginEntry $entry, AdminPluginVisibilitySnapshot $visibility ) :array {
		$reasons = [];

		if ( !isset( $visibility->wpDiscoveredMuPlugins[ $entry->file ] ) ) {
			$reasons[] = HiddenReason::WpDiscoveryCacheGap;
		}
		elseif ( !$visibility->showMustUsePlugins ) {
			$reasons[] = HiddenReason::ShowAdvancedPlugins;
		}

		if ( $visibility->showMustUsePlugins
			 && isset( $visibility->adminMustUsePlugins[ $entry->file ] )
			 && !$visibility->isVisibleInFinalList( $entry ) ) {
			$reasons[] = HiddenReason::PluginsList;
		}

		return $reasons;
	}
}
