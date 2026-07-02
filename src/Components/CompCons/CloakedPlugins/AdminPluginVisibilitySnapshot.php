<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

class AdminPluginVisibilitySnapshot {

	public array $wpDiscoveredPlugins;

	public array $adminAllPlugins;

	public array $wpDiscoveredMuPlugins;

	public bool $showMustUsePlugins;

	public array $adminMustUsePlugins;

	public ?array $finalPluginsList;

	public array $activePlugins;

	public array $networkActivePlugins;

	public function __construct(
		array $wpDiscoveredPlugins,
		array $adminAllPlugins,
		array $wpDiscoveredMuPlugins,
		bool $showMustUsePlugins,
		array $adminMustUsePlugins,
		?array $finalPluginsList,
		array $activePlugins,
		array $networkActivePlugins
	) {
		$this->wpDiscoveredPlugins = $wpDiscoveredPlugins;
		$this->adminAllPlugins = $adminAllPlugins;
		$this->wpDiscoveredMuPlugins = $wpDiscoveredMuPlugins;
		$this->showMustUsePlugins = $showMustUsePlugins;
		$this->adminMustUsePlugins = $adminMustUsePlugins;
		$this->finalPluginsList = $finalPluginsList;
		$this->activePlugins = $activePlugins;
		$this->networkActivePlugins = $networkActivePlugins;
	}

	public function isActive( string $file ) :bool {
		return \in_array( $file, $this->activePlugins, true );
	}

	public function isNetworkActive( string $file ) :bool {
		return \in_array( $file, $this->networkActivePlugins, true );
	}

	public function isVisibleInFinalList( PluginEntry $entry ) :bool {
		if ( $this->finalPluginsList === null ) {
			return true;
		}

		return $entry->type === PluginType::MustUse ?
			isset( $this->finalPluginsList[ 'mustuse' ][ $entry->file ] )
			: $this->isStandardVisibleInFinalList( $entry->file );
	}

	private function isStandardVisibleInFinalList( string $file ) :bool {
		foreach ( [
			'all',
			'active',
			'inactive',
			'recently_activated',
			'upgrade',
			'paused',
			'auto-update-enabled',
			'auto-update-disabled',
		] as $group ) {
			if ( isset( $this->finalPluginsList[ $group ][ $file ] ) ) {
				return true;
			}
		}

		return false;
	}
}
