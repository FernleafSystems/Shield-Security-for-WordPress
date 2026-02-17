<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\Exceptions\{
	AcquireMoException,
	AcquireMoHashMismatchException,
	AcquireMoInvalidFileException,
	AcquireMoNoCachePathException,
	AcquireMoWriteFailedException
};
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Translations\{
	DownloadTranslation,
	ListAvailable
};
use FernleafSystems\Wordpress\Services\Services;

class TranslationDownloadController {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	private const OPT_KEY = 'translation_config';
	private const DOWNLOAD_COOLDOWN = 600;

	private static bool $fetching = false;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$this->scheduleCrons();
		$this->setupCronHooks();
	}

	private function scheduleCrons() :void {
		$con = self::con();
		$now = Services::Request()->ts();
		$localesLookup = $con->prefix( 'adhoc_locales_check' );
		if ( empty( $this->getCachedLocales() ) ) {
			add_action( $localesLookup, fn() => $this->getAvailableLocales() );
			if ( !Services::WpGeneral()->isCron() && !wp_next_scheduled( $localesLookup ) ) {
				wp_schedule_single_event( $now + \MINUTE_IN_SECONDS, $localesLookup );
			}
		}
		$localesDownload = $con->prefix( 'adhoc_locales_download' );
		if ( !empty( $this->getQueue() ) ) {
			add_action( $localesDownload, fn() => $this->processQueue() );
			if ( !Services::WpGeneral()->isCron() && !wp_next_scheduled( $localesDownload ) ) {
				// schedule the next download to align with the end of the cooldown window.
				$timeTilNextDownload = \max( 0, self::DOWNLOAD_COOLDOWN - ( $now - ( $this->cfg()[ 'last_download_at' ] ?? 0 ) ) );
				wp_schedule_single_event( $now + $timeTilNextDownload + 10, $localesDownload );
			}
		}
	}

	public function runDailyCron() {
		$this->queueStaleCachedLocalesForDownload();
	}

	public function queueStaleCachedLocalesForDownload() :void {
		$available = $this->getAvailableLocales();
		foreach ( \array_keys( $available ) as $locale ) {
			$localeData = $this->getLocaleMeta( $available, $locale );
			if ( \is_array( $localeData ) && $this->getLocaleMoFilePath( $locale ) !== null ) {
				[ 'hash' => $remoteHash, 'hash_type' => $hashType ] = $localeData;
				if ( !\hash_equals( $this->calculateLocalHash( $locale, $hashType ), $remoteHash ) ) {
					$this->enqueueLocaleForDownload( $locale );
				}
			}
		}
	}

	/**
	 * There is no validation of the locale at this stage. It will be filtered and validated later.
	 */
	public function enqueueLocaleForDownload( string $locale ) :void {
		$this->saveQueue( \array_merge( $this->getQueue(), [ $locale ] ) );
	}

	public function processQueue( bool $force = false ) :void {
		if ( !empty( $this->getQueue() ) && ( $force || $this->canAttemptDownload() ) ) {

			$this->addCfg( 'last_download_at', Services::Request()->ts() );

			$processed = [];
			$available = $this->getAvailableLocales();

			foreach ( $this->getQueue() as $locale ) {
				$localeData = $this->getLocaleMeta( $available, $locale );
				$processed[] = $locale;

				if ( \is_array( $localeData ) ) {
					[ 'hash' => $remoteHash, 'hash_type' => $hashType ] = $localeData;
					$localHash = $this->calculateLocalHash( $locale, $hashType );

					if ( !\hash_equals( $localHash, $remoteHash )
						 && !$this->acquireMoWithSingleRefreshRetry( $locale, $remoteHash, $hashType ) ) {
						\array_pop( $processed );
					}
				}
			}

			$this->saveQueue( \array_diff( $this->getQueue(), $processed ) );
		}
	}

	public function getLocaleMoFilePath( string $locale ) :?string {
		$path = $this->buildMoFilePath( $locale );
		return ( !empty( $path ) && Services::WpFs()->isAccessibleFile( $path ) ) ? $path : null;
	}

	public function isLocaleAvailable( string $locale ) :bool {
		return !empty( $this->getCachedLocales()[ $locale ] );
	}

	/**
	 * Returns cached locales without triggering API calls.
	 * Safe to call in any context, including during textdomain loading.
	 */
	public function getCachedLocales() :array {
		return \is_array( $this->cfg()[ 'locales' ] ?? null ) ? $this->cfg()[ 'locales' ] : [];
	}

	private function getLocaleMeta( array $locales, string $locale ) :?array {
		$localeData = $locales[ $locale ] ?? null;
		return ( \is_array( $localeData )
				 && !empty( $localeData[ 'hash' ] ) && \is_string( $localeData[ 'hash' ] )
				 && !empty( $localeData[ 'hash_type' ] ) && \is_string( $localeData[ 'hash_type' ] )
				 && \in_array( $localeData[ 'hash_type' ], \hash_algos(), true ) )
			? [
				'hash'      => $localeData[ 'hash' ],
				'hash_type' => $localeData[ 'hash_type' ],
			]
			: null;
	}

	private function calculateLocalHash( string $locale, string $hashType ) :string {
		$localHash = '';
		$localPath = $this->getLocaleMoFilePath( $locale );
		if ( $localPath !== null ) {
			$localContent = Services::WpFs()->getFileContent( $localPath );
			if ( !empty( $localContent ) ) {
				$localHash = \hash( $hashType, $localContent );
			}
		}
		return $localHash;
	}

	private function acquireMoWithSingleRefreshRetry( string $locale, string $remoteHash, string $hashType ) :bool {
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			try {
				$this->acquireMo( $locale, $remoteHash, $hashType );
				return true;
			}
			catch ( AcquireMoException $e ) {
				if ( !$e instanceof AcquireMoHashMismatchException
					 || $attempt > 0
					 || !$this->canForceRefreshLocalesAfterHashMismatch() ) {
					$this->fireDownloadFailedEvent( $locale, $e->reason() );
					return false;
				}

				$freshMeta = $this->getLocaleMeta( $this->getAvailableLocales( true ), $locale );
				if ( !\is_array( $freshMeta ) ) {
					$this->fireDownloadFailedEvent( $locale, 'missing_locale_meta_after_hash_mismatch' );
					return false;
				}

				$remoteHash = $freshMeta[ 'hash' ];
				$hashType = $freshMeta[ 'hash_type' ];
			}
		}

		return false;
	}

	private function acquireMo( string $locale, string $expectedHash, string $hashAlgo ) :void {
		$path = $this->buildMoFilePath( $locale );
		if ( empty( $path ) ) {
			throw new AcquireMoNoCachePathException();
		}

		$cacheDir = \dirname( $path );
		if ( !Services::WpFs()->isAccessibleDir( $cacheDir ) ) {
			throw new AcquireMoNoCachePathException();
		}

		$content = ( new DownloadTranslation() )->download( $locale );

		if ( empty( $content ) || !$this->isValidMo( $content ) ) {
			throw new AcquireMoInvalidFileException();
		}
		elseif ( !\hash_equals( $expectedHash, \hash( $hashAlgo, $content ) ) ) {
			throw new AcquireMoHashMismatchException();
		}

		if ( !Services::WpFs()->putFileContent( $path, $content ) ) {
			throw new AcquireMoWriteFailedException();
		}

		self::con()->comps->events->fireEvent( 'translation_downloaded', [
			'audit_params' => [ 'locale' => $locale, ],
		] );
	}

	private function fireDownloadFailedEvent( string $locale, string $reason ) :void {
		self::con()->comps->events->fireEvent( 'translation_download_failed', [
			'audit_params' => [
				'locale' => $locale,
				'reason' => $reason,
			],
		] );
	}

	private function buildMoFilePath( string $locale ) :string {
		$cacheDir = self::con()->cache_dir_handler->buildSubDir( 'languages' );
		return empty( $cacheDir ) ? ''
			: path_join( $cacheDir, \sprintf( '%s-%s.mo', self::con()->getTextDomain(), $locale ) );
	}

	/**
	 * .mo files start with a magic number 0x950412de or 0xde120495 (little/big endian)
	 */
	private function isValidMo( string $content ) :bool {
		$valid = false;
		if ( \strlen( $content ) >= 4 ) {
			$magic = \unpack( 'N', \substr( $content, 0, 4 ) )[ 1 ];
			$valid = $magic === 0x950412de || $magic === 0xde120495;
		}
		return $valid;
	}

	private function canAttemptDownload() :bool {
		return ( Services::Request()->ts() - ( $this->cfg()[ 'last_download_at' ] ?? 0 ) ) >= self::DOWNLOAD_COOLDOWN;
	}

	private function canForceRefreshLocalesAfterHashMismatch() :bool {
		return ( Services::Request()->ts() - ( $this->cfg()[ 'last_fetch_at' ] ?? 0 ) ) >= \HOUR_IN_SECONDS;
	}

	public function cfg() :array {
		return self::con()->opts->optGet( self::OPT_KEY ) ?: [];
	}

	private function addCfg( string $key, $value ) :void {
		$cfg = $this->cfg();
		$cfg[ $key ] = $value;
		self::con()->opts->optSet(
			self::OPT_KEY,
			\array_intersect_key( $cfg, \array_flip( [ 'queue', 'locales', 'last_fetch_at', 'last_download_at' ] ) )
		)->store();
	}

	public function isQueueRelevantToLocale( string $locale ) :bool {
		$isRelevant = false;
		$lang = \substr( $locale, 0, 2 );
		foreach ( $this->getQueue() as $queuedLocale ) {
			if ( $locale === $queuedLocale || $lang === \substr( $queuedLocale, 0, 2 ) ) {
				$isRelevant = true;
				break;
			}
		}
		return $isRelevant;
	}

	public function getQueue() :array {
		return $this->cfg()[ 'queue' ] ?? [];
	}

	private function saveQueue( array $queue ) :void {
		$this->addCfg( 'queue',
			\array_values( \array_filter( \array_unique( $queue ), fn( $loc ) => !empty( $loc ) && $this->isLocaleAvailable( $loc ) ) )
		);
	}

	/**
	 * Be sure to only ever call this on a cron or non-synchronous request.
	 */
	private function getAvailableLocales( bool $forceCheck = false ) :array {
		$locales = $this->getCachedLocales();
		$cacheTTL = ( self::con()->cfg->translations[ 'list_cache_hours' ] ?? 24 )*\HOUR_IN_SECONDS;

		$isInvalid = empty( $locales )
					 || ( Services::Request()->ts() - ( $this->cfg()[ 'last_fetch_at' ] ?? 0 ) ) >= $cacheTTL;

		if ( $forceCheck || ( $isInvalid && !self::$fetching ) ) {
			self::$fetching = true;
			try {
				$this->addCfg( 'last_fetch_at', Services::Request()->ts() );
				$apiLocales = ( new ListAvailable() )->retrieve();
				$this->addCfg( 'locales', ( !empty( $apiLocales ) && \is_array( $apiLocales ) ) ? $apiLocales : [] );
				$locales = $this->cfg()[ 'locales' ];
			}
			finally {
				self::$fetching = false;
			}
		}

		return $locales;
	}
}
