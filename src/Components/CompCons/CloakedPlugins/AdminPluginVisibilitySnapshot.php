<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

class AdminPluginVisibilitySnapshot {

	public ?bool $showMustUsePlugins;

	public function __construct(
		public ?array $wpDiscoveredPlugins,
		public ?array $adminAllPlugins,
		public array $wpDiscoveredMuPlugins,
		bool $showMustUsePlugins,
		public ?array $finalPluginsList,
		public array $activePlugins,
		public array $networkActivePlugins,
		public bool $isPageObservation = false
	) {
		$this->showMustUsePlugins = $showMustUsePlugins;
	}

	public function isActive( string $file ) :bool {
		return \in_array( $file, $this->activePlugins, true );
	}

	public function isNetworkActive( string $file ) :bool {
		return \in_array( $file, $this->networkActivePlugins, true );
	}

	public function canCompare( string $type ) :bool {
		if ( $type === PluginType::MustUse ) {
			return $this->isPluginMap( $this->wpDiscoveredMuPlugins )
				&& ( !$this->isPageObservation || ( $this->showMustUsePlugins !== null
					&& $this->isPluginMap( $this->finalPluginsList[ 'mustuse' ] ?? null ) ) );
		}

		if ( empty( $this->wpDiscoveredPlugins ) || !$this->isPluginMap( $this->wpDiscoveredPlugins ) ) {
			return false;
		}
		if ( !$this->isPageObservation ) {
			return true;
		}
		if ( empty( $this->adminAllPlugins ) || !$this->isPluginMap( $this->adminAllPlugins )
			|| empty( $this->finalPluginsList[ 'all' ] ) ) {
			return false;
		}
		foreach ( [ 'all', 'active', 'inactive', 'recently_activated', 'upgrade', 'paused' ] as $group ) {
			if ( !$this->isPluginMap( $this->finalPluginsList[ $group ] ?? null ) ) {
				return false;
			}
		}
		foreach ( [ 'auto-update-enabled', 'auto-update-disabled' ] as $group ) {
			if ( \array_key_exists( $group, $this->finalPluginsList )
				&& !$this->isPluginMap( $this->finalPluginsList[ $group ] ) ) {
				return false;
			}
		}
		return true;
	}

	public function canReplaceFindings( string $type ) :bool {
		return $this->isPageObservation && $this->canCompare( $type );
	}

	private function isPluginMap( mixed $plugins ) :bool {
		if ( !\is_array( $plugins ) ) {
			return false;
		}
		foreach ( $plugins as $file => $data ) {
			if ( !\is_string( $file ) || $file === '' || !\is_array( $data ) ) {
				return false;
			}
		}
		return true;
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
