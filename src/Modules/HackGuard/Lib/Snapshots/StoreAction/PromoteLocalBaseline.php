<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	NormalizeHashMap,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	Build\BuildHashesFromApi,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class PromoteLocalBaseline extends BaseAction {

	private const CHECK_INTERVAL = 86400;

	/**
	 * @param array{meta:array,data:array<string,string>}|null $snapshot
	 */
	public static function isDue( ?array $snapshot, int $now ) :bool {
		if ( $snapshot === null || ( $snapshot[ 'meta' ][ 'live_hashes' ] ?? false ) === true ) {
			return false;
		}

		$lastCheck = $snapshot[ 'meta' ][ 'last_live_hash_check_at' ] ?? null;
		return !\is_int( $lastCheck )
			   || $lastCheck < 0
			   || $lastCheck <= $now - self::CHECK_INTERVAL;
	}

	public function run() :bool {
		$target = $this->describeAsset( $this->getAsset() );
		if ( $target === null ) {
			return false;
		}

		$asset = $this->loadExactAsset( $target );
		if ( $asset === null || !$this->isAfsIdle() ) {
			return false;
		}

		$attemptedAt = Services::Request()->ts();
		try {
			$store = ( new Load() )
				->setAsset( $asset )
				->run();
			$original = $store->getUsableSnapshot();
		}
		catch ( \Throwable $e ) {
			return false;
		}
		if ( $original === null || !self::isDue( $original, $attemptedAt ) ) {
			return false;
		}

		try {
			$published = ( new NormalizeHashMap() )->toScalarMap(
				( new BuildHashesFromApi() )->build( $asset )
			);
		}
		catch ( \Throwable $e ) {
			$published = [];
		}

		$liveAsset = $this->loadExactAsset( $target );
		if ( $liveAsset === null ) {
			return false;
		}
		$this->setAsset( $liveAsset );

		if ( empty( $published ) ) {
			$this->recordCompletedCheck( $original[ 'data' ], $original[ 'meta' ], $attemptedAt );
			return false;
		}

		$publishedMeta = $this->generateMeta();
		$publishedMeta[ 'live_hashes' ] = true;
		if ( !$this->isAfsIdle() ) {
			return false;
		}

		try {
			$store
				->setSnapData( $published )
				->setSnapMeta( $publishedMeta )
				->save();
			$persisted = $this->loadUsableSnapshot();
			if ( $persisted[ 'data' ] !== $published
				 || $persisted[ 'meta' ] !== $publishedMeta ) {
				throw new \RuntimeException( 'Published snapshot verification failed.' );
			}
		}
		catch ( \Throwable $e ) {
			if ( $this->restoreOriginal( $store, $original[ 'data' ], $original[ 'meta' ] ) ) {
				$this->recordCompletedCheck( $original[ 'data' ], $original[ 'meta' ], $attemptedAt );
			}
			else {
				error_log( 'Shield snapshot promotion could not restore the original local baseline.' );
			}
			return false;
		}

		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		self::con()->comps->asset_coordinator->enqueuePromotionFollowUp(
			$target[ 'type' ],
			$target[ 'key' ],
			$target[ 'version' ]
		);

		return true;
	}

	/**
	 * @param mixed $asset
	 * @return array{type:string,key:string,version:string}|null
	 */
	private function describeAsset( $asset ) :?array {
		if ( $asset instanceof WpPluginVo && $asset->asset_type === 'plugin' ) {
			$type = 'plugin';
			$key = $asset->file;
		}
		elseif ( $asset instanceof WpThemeVo && $asset->asset_type === 'theme' ) {
			$type = 'theme';
			$key = $asset->stylesheet;
		}
		else {
			return null;
		}

		$version = $asset->version;
		return \is_string( $key )
			   && trim( $key ) !== ''
			   && \strpos( $key, "\0" ) === false
			   && \is_string( $version )
			   && trim( $version ) !== ''
			   && \strpos( $version, "\0" ) === false
			? [
				'type'    => $type,
				'key'     => $key,
				'version' => $version,
			]
			: null;
	}

	/**
	 * @param array{type:string,key:string,version:string} $target
	 * @return WpPluginVo|WpThemeVo|null
	 */
	private function loadExactAsset( array $target ) {
		try {
			$asset = $target[ 'type' ] === 'plugin'
				? Services::WpPlugins()->getPluginAsVo( $target[ 'key' ], true )
				: Services::WpThemes()->getThemeAsVo( $target[ 'key' ], true );
			$current = $this->describeAsset( $asset );
			return $current !== null && $current === $target ? $asset : null;
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}

	/** @phpstan-impure */
	private function isAfsIdle() :bool {
		try {
			return !( new ScansStatus() )->hasActiveAfs();
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * @param array<string,string> $originalData
	 */
	private function recordCompletedCheck( array $originalData, array $originalMeta, int $attemptedAt ) :void {
		$checkedMeta = $originalMeta;
		$checkedMeta[ 'last_live_hash_check_at' ] = $attemptedAt;
		$writeAttempted = false;

		try {
			$current = $this->loadUsableSnapshot();
			if ( $current[ 'data' ] !== $originalData || $current[ 'meta' ] !== $originalMeta ) {
				return;
			}

			$store = $this->getNewStore( false );
			$writeAttempted = true;
			if ( !$store->setSnapMeta( $checkedMeta )->saveMeta() ) {
				throw new \RuntimeException( 'The local baseline check timestamp could not be saved.' );
			}

			$persisted = $this->loadUsableSnapshot();
			if ( $persisted[ 'data' ] !== $originalData || $persisted[ 'meta' ] !== $checkedMeta ) {
				throw new \RuntimeException( 'The local baseline check timestamp could not be verified.' );
			}
			return;
		}
		catch ( \Throwable $e ) {
			if ( !$writeAttempted ) {
				error_log( 'Shield snapshot promotion could not persist its completed-check timestamp.' );
				return;
			}
			try {
				$restoredMeta = $this->getNewStore( false )
					->setSnapMeta( $originalMeta )
					->saveMeta();
				$restored = $this->loadUsableSnapshot();
				if ( !$restoredMeta
					 || $restored[ 'data' ] !== $originalData
					 || $restored[ 'meta' ] !== $originalMeta ) {
					throw new \RuntimeException( 'The original local-baseline metadata could not be restored.' );
				}
			}
			catch ( \Throwable $restoreError ) {
				unset( $restoreError );
			}
			error_log( 'Shield snapshot promotion could not persist its completed-check timestamp.' );
		}
	}

	/**
	 * @param array<string,string> $originalData
	 */
	private function restoreOriginal( Store $store, array $originalData, array $originalMeta ) :bool {
		try {
			$store
				->setSnapData( $originalData )
				->setSnapMeta( $originalMeta )
				->save();
			$restored = $this->loadUsableSnapshot();
			return $restored[ 'data' ] === $originalData
				   && $restored[ 'meta' ] === $originalMeta;
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * @return array{meta:array,data:array<string,string>}
	 */
	private function loadUsableSnapshot() :array {
		$snapshot = ( new Load() )
			->setAsset( $this->getAsset() )
			->run()
			->getUsableSnapshot();
		if ( $snapshot === null ) {
			throw new \RuntimeException( 'Snapshot is unusable.' );
		}
		return $snapshot;
	}
}
