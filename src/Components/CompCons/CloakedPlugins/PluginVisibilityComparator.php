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

		foreach ( $entries as $entry ) {
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
		elseif ( !isset( $visibility->adminAllPlugins[ $entry->file ] ) ) {
			$reasons[] = CloakReason::AllPlugins;
		}

		if ( isset( $visibility->adminAllPlugins[ $entry->file ] ) && !$visibility->isVisibleInFinalList( $entry ) ) {
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
		elseif ( !$visibility->showMustUsePlugins ) {
			$reasons[] = CloakReason::ShowAdvancedPlugins;
		}

		if ( $visibility->showMustUsePlugins
			 && isset( $visibility->adminMustUsePlugins[ $entry->file ] )
			 && !$visibility->isVisibleInFinalList( $entry ) ) {
			$reasons[] = CloakReason::PluginsList;
		}

		return $reasons;
	}
}
