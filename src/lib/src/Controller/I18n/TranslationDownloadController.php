<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

use FernleafSystems\Utilities\Logic\ExecOnce;
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

	private static bool $fetching = false;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$this->setupCronHooks();
	}

	/**
	 * There is no validation of the locale at this stage. It will be filtered and validated later.
	 */
	public function enqueueLocaleForDownload( string $locale ) :void {
		$this->saveQueue( \array_merge( $this->getQueue(), [ $locale ] ) );
	}

	/**
	 * Daily cron: process queued locales.
	 */
	public function runHourlyCron() :void {
		$this->processQueue();
	}

	public function processQueue() :void {
		if ( !empty( $this->getQueue() ) && $this->canAttemptDownload() ) {

			$this->addCfg( 'last_attempt_at', Services::Request()->ts() );

			$processed = [];
			$available = $this->getAvailableLocales();

			foreach ( $this->getQueue() as $locale ) {
				$localeData = $available[ $locale ] ?? null;
				$processed[] = $locale;

				if ( \is_array( $localeData ) && !empty( $localeData[ 'hash' ] ) && !empty( $localeData[ 'hash_type' ] ) ) {
					[ 'hash' => $remoteHash, 'hash_type' => $hashType ] = $localeData;
					$localPath = $this->getLocaleMoFilePath( $locale );
					$localHash = '';
					if ( $localPath !== null ) {
						$localContent = Services::WpFs()->getFileContent( $localPath );
						if ( !empty( $localContent ) ) {
							$localHash = \hash( $hashType, $localContent );
						}
					}

					if ( !\hash_equals( $localHash, $remoteHash ) && !$this->acquireMo( $locale, $remoteHash, $hashType ) ) {
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
		return $this->cfg()[ 'available' ][ 'locales' ] ?? [];
	}

	private function acquireMo( string $locale, string $expectedHash, string $hashAlgo ) :bool {
		$content = ( new DownloadTranslation() )->download( $locale );

		$success = true;
		if ( empty( $content ) || !$this->isValidMo( $content ) ) {
			$this->fireDownloadFailedEvent( $locale, 'invalid_file' );
			$success = false;
		}
		elseif ( !\hash_equals( $expectedHash, \hash( $hashAlgo, $content ) ) ) {
			$this->fireDownloadFailedEvent( $locale, 'hash_mismatch' );
			$success = false;
		}

		if ( $success ) {
			$path = $this->buildMoFilePath( $locale );
			if ( empty( $path ) ) {
				$this->fireDownloadFailedEvent( $locale, 'no_cache_path' );
				$success = false;
			}
			elseif ( !Services::WpFs()->putFileContent( $path, $content ) ) {
				$this->fireDownloadFailedEvent( $locale, 'write_failed' );
				$success = false;
			}
			else {
				self::con()->comps->events->fireEvent( 'translation_downloaded', [
					'audit_params' => [ 'locale' => $locale, ],
				] );
			}
		}
		return $success;
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
		$lastAttempt = $this->cfg()[ 'last_attempt_at' ] ?? 0;
		return ( Services::Request()->ts() - $lastAttempt )
			   >= self::con()->cfg->translations[ 'download_cooldown_days' ]*\DAY_IN_SECONDS;
	}

	private function cfg() :array {
		return self::con()->opts->optGet( self::OPT_KEY ) ?: [];
	}

	private function addCfg( string $key, $value ) :void {
		$cfg = $this->cfg();
		$cfg[ $key ] = $value;
		self::con()->opts->optSet( self::OPT_KEY, $cfg )->store();
	}

	private function getQueue() :array {
		return $this->cfg()[ 'queue' ] ?? [];
	}

	private function saveQueue( array $queue ) :void {
		$this->addCfg( 'queue', \array_filter( \array_values( \array_unique( $queue ) ) ) );
	}

	public function getAvailableLocales() :array {
		$available = $this->cfg()[ 'available' ] ?? null;
		$cacheTTL = ( self::con()->cfg->translations[ 'list_cache_hours' ] ?? 24 )*\HOUR_IN_SECONDS;

		$isInvalid = !\is_array( $available )
					 || empty( $available[ 'fetched_at' ] )
					 || ( Services::Request()->ts() - $available[ 'fetched_at' ] ) >= $cacheTTL;

		if ( $isInvalid && !self::$fetching ) {
			self::$fetching = true;
			try {
				$this->fetchLocalesFromApi();
				$available = $this->cfg()[ 'available' ];
			}
			finally {
				self::$fetching = false;
			}
		}

		return $available[ 'locales' ] ?? [];
	}

	private function fetchLocalesFromApi() :void {
		$api = ( new ListAvailable() )->retrieve();
		$locales = ( \is_array( $api ) && \is_array( $api[ 'locales' ] ?? null ) ) ? $api[ 'locales' ] : [];
		$available = [
			'locales'    => $locales,
			// Always update fetched_at to prevent API hammering on failure
			'fetched_at' => Services::Request()->ts(),
		];
		$this->addCfg( 'available', $available );
	}
}
