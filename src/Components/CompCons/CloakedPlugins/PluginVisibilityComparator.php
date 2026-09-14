<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

class PluginVisibilityComparator {

	/**
	 * @param list<PluginEntry> $entries
	 * @return list<CloakedPluginFinding>
	 */
	public function compare( array $entries, AdminPluginVisibilitySnapshot $visibility ) :array {
		$findings = [];
		$detectedAt = \time();
		$comparableTypes = \array_filter( PluginType::ALL, [ $visibility, 'canCompare' ] );

		foreach ( $entries as $entry ) {
			if ( !\in_array( $entry->type, $comparableTypes, true ) ) {
				continue;
			}
			$reasons = $entry->type === PluginType::MustUse ?
				$this->mustUseCloakReasons( $entry, $visibility )
				: $this->standardCloakReasons( $entry, $visibility );

			if ( !empty( $reasons ) ) {
				$findings[] = new CloakedPluginFinding(
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
	 * @phpstan-return list<value-of<CloakReason::ALL>>
	 */
	private function standardCloakReasons( PluginEntry $entry, AdminPluginVisibilitySnapshot $visibility ) :array {
		$reasons = [];

		if ( !isset( $visibility->wpDiscoveredPlugins[ $entry->file ] ) ) {
			$reasons[] = CloakReason::WpDiscoveryCacheGap;
		}
		elseif ( $visibility->isPageObservation && !isset( $visibility->adminAllPlugins[ $entry->file ] ) ) {
			$reasons[] = CloakReason::AllPlugins;
		}

		if ( $visibility->isPageObservation && isset( $visibility->adminAllPlugins[ $entry->file ] ) && !$visibility->isVisibleInFinalList( $entry ) ) {
			$reasons[] = CloakReason::PluginsList;
		}

		return $reasons;
	}

	/**
	 * @phpstan-return list<value-of<CloakReason::ALL>>
	 */
	private function mustUseCloakReasons( PluginEntry $entry, AdminPluginVisibilitySnapshot $visibility ) :array {
		$reasons = [];

		if ( !isset( $visibility->wpDiscoveredMuPlugins[ $entry->file ] ) ) {
			$reasons[] = CloakReason::WpDiscoveryCacheGap;
		}
		elseif ( $visibility->isPageObservation && !$visibility->showMustUsePlugins ) {
			$reasons[] = CloakReason::ShowAdvancedPlugins;
		}

		if ( $visibility->isPageObservation && $visibility->showMustUsePlugins
			 && isset( $visibility->wpDiscoveredMuPlugins[ $entry->file ] )
			 && !$visibility->isVisibleInFinalList( $entry ) ) {
			$reasons[] = CloakReason::PluginsList;
		}

		return $reasons;
	}
}
