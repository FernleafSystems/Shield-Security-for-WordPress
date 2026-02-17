<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Translations\{
	DownloadTranslation,
	ListAvailable
};
use FernleafSystems\Wordpress\Services\Services;

class Translations extends BaseCmd {

	protected function cmdParts() :array {
		return [ 'translations' ];
	}

	protected function cmdShortDescription() :string {
		return 'Manage Shield plugin translation downloads.';
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'action',
				'options'     => [
					'list',
					'queue',
					'download',
					'status',
					'verify',
				],
				'default'     => 'status',
				'optional'    => false,
				'description' => 'Action to perform on translations.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'locale',
				'optional'    => true,
				'description' => 'Locale code for download action (e.g., de_DE, fr_FR).',
			],
			[
				'type'        => 'flag',
				'name'        => 'force',
				'optional'    => true,
				'description' => 'Force download even if already cached.',
			],
		];
	}

	public function runCmd() :void {
		switch ( $this->execCmdArgs[ 'action' ] ) {
			case 'list':
				$this->runList();
				break;
			case 'queue':
				$this->runQueue();
				break;
			case 'download':
				$this->runDownload();
				break;
			case 'verify':
				$this->runVerify();
				break;
			case 'status':
			default:
				$this->runStatus();
				break;
		}
	}

	private function runList() :void {
		$api = new ListAvailable();
		$locales = $api->retrieve();

		if ( empty( $locales ) ) {
			\WP_CLI::warning( __( 'No locales available from API.', 'wp-simple-firewall' ) );
			return;
		}

		$items = [];
		foreach ( $locales as $locale => $data ) {
			$items[] = [
				'locale'     => $locale,
				'hash'       => \substr( $data[ 'hash' ] ?? '-', 0, 20 ).'...',
				'size'       => $data[ 'size' ] ?? '-',
				'updated_at' => !empty( $data[ 'updated_at' ] )
					? \date( 'Y-m-d H:i:s', $data[ 'updated_at' ] )
					: '-',
			];
		}

		\WP_CLI\Utils\format_items( 'table', $items, [ 'locale', 'hash', 'size', 'updated_at' ] );
		\WP_CLI::success( \sprintf( __( '%d locale(s) available.', 'wp-simple-firewall' ), \count( $items ) ) );
	}

	private function runQueue() :void {
		$controller = self::con()->comps->translation_downloads;

		// Access the queue via reflection since getQueue() is private
		$reflection = new \ReflectionClass( $controller );
		$method = $reflection->getMethod( 'getQueue' );
		$method->setAccessible( true );
		$queue = $method->invoke( $controller );

		if ( empty( $queue ) ) {
			\WP_CLI::success( __( 'Translation download queue is empty.', 'wp-simple-firewall' ) );
			return;
		}

		$items = \array_map( fn( $locale ) => [ 'locale' => $locale ], $queue );
		\WP_CLI\Utils\format_items( 'table', $items, [ 'locale' ] );
		\WP_CLI::success( \sprintf( __( '%d locale(s) in queue.', 'wp-simple-firewall' ), \count( $queue ) ) );
	}

	private function runDownload() :void {
		$locale = $this->execCmdArgs[ 'locale' ] ?? '';

		if ( empty( $locale ) ) {
			\WP_CLI::error( __( 'Please specify a locale code (e.g., --locale=de_DE).', 'wp-simple-firewall' ) );
			return;
		}

		$controller = self::con()->comps->translation_downloads;

		if ( !$controller->isLocaleAvailable( $locale ) ) {
			\WP_CLI::error( \sprintf( __( 'Locale "%s" is not available for download.', 'wp-simple-firewall' ), $locale ) );
			return;
		}

		// Check if already cached and not forcing
		$cachedPath = $controller->getLocaleMoFilePath( $locale );
		if ( !empty( $cachedPath ) && !$this->isForceFlag() ) {
			\WP_CLI::warning( \sprintf(
				__( 'Locale "%1$s" is already cached at: %2$s. Use --force to re-download.', 'wp-simple-firewall' ),
				$locale,
				$cachedPath
			) );
			return;
		}

		// Queue the locale and run the cron immediately
		$controller->enqueueLocaleForDownload( $locale );

		\WP_CLI::log( \sprintf( __( 'Downloading translation for locale: %s', 'wp-simple-firewall' ), $locale ) );
		$controller->processQueue( true );

		// Check if download succeeded
		$cachedPath = $controller->getLocaleMoFilePath( $locale );
		if ( !empty( $cachedPath ) ) {
			\WP_CLI::success( \sprintf( __( 'Translation downloaded successfully: %s', 'wp-simple-firewall' ), $cachedPath ) );
		}
		else {
			\WP_CLI::error( __( 'Download failed. Check that the ShieldNet API is available and the locale exists.', 'wp-simple-firewall' ) );
		}
	}

	private function runStatus() :void {
		$con = self::con();
		$tranCon = $con->comps->translation_downloads;

		\WP_CLI::log( '=== Translation Download Status ===' );
		\WP_CLI::log( '' );

		\WP_CLI::log( \sprintf( 'Cache Directory: %s', $con->cache_dir_handler->buildSubDir( 'languages' ) ?: 'Not configured' ) );
		if ( \count( $tranCon->getQueue() ) > 0 ) {
			\WP_CLI::log( \sprintf( 'Queued Locales: %s', \implode( ', ', $tranCon->getQueue() ) ) );
		}
		else {
			\WP_CLI::log( 'Queued Locales: none' );
		}

		// Show last attempt time
		$lastAttempt = $tranCon->cfg()[ 'last_download_at' ] ?? 0;
		\WP_CLI::log( \sprintf( 'Last Download Attempt: %s',
			$lastAttempt > 0 ? Services::WpGeneral()->getTimeStampForDisplay( $lastAttempt ) : 'Never'
		) );
		\WP_CLI::log( '' );

		// Show available locales from cache
		$available = $tranCon->getCachedLocales();
		if ( !empty( $available ) ) {
			\WP_CLI::log( '--- Cached Locale Files ---' );
			$items = [];
			foreach ( \array_keys( $available ) as $locale ) {
				$path = $tranCon->getLocaleMoFilePath( $locale );
				if ( !empty( $path ) ) {
					$items[] = [
						'locale'      => $locale,
						'file_exists' => 'Yes',
						'path'        => $path,
					];
				}
			}
			if ( !empty( $items ) ) {
				\WP_CLI\Utils\format_items( 'table', $items, [ 'locale', 'file_exists', 'path' ] );
			}
			else {
				\WP_CLI::log( 'No cached translation files found.' );
			}
		}

		if ( !empty( $available ) ) {
			\WP_CLI::log( '--- Cached Locale Meta Data ---' );
			$items = [];
			foreach ( $available as $l => $av ) {
				$av[ 'locale' ] = $l;
				$items[] = $av;
			}
			if ( !empty( $items ) ) {
				\WP_CLI\Utils\format_items( 'table', $items, [ 'locale', 'hash', 'hash_type', 'size' ] );
			}
			else {
				\WP_CLI::log( 'No cached meta data found.' );
			}
		}

		\WP_CLI::success( __( 'Status check complete.', 'wp-simple-firewall' ) );
	}

	private function runVerify() :void {
		\WP_CLI::log( 'Fetching available locales from ShieldNet API...' );

		$api = new ListAvailable();
		$locales = $api->retrieve();

		if ( empty( $locales ) ) {
			\WP_CLI::error( 'Could not fetch locale list from API.' );
			return;
		}

		\WP_CLI::log( \sprintf( 'Found %d locale(s). Verifying hashes...', \count( $locales ) ) );
		\WP_CLI::log( '' );

		$downloader = new DownloadTranslation();
		$passed = 0;
		$failed = 0;
		$skipped = 0;
		$results = [];

		foreach ( $locales as $locale => $data ) {
			$apiHash = $data[ 'hash' ] ?? '';
			$hashType = $data[ 'hash_type' ] ?? 'sha256';

			if ( empty( $apiHash ) ) {
				$results[] = [ 'locale' => $locale, 'status' => 'SKIP', 'reason' => 'No hash in API' ];
				$skipped++;
				continue;
			}

			$content = $downloader->download( $locale );
			if ( empty( $content ) ) {
				$results[] = [ 'locale' => $locale, 'status' => 'FAIL', 'reason' => 'Download failed' ];
				$failed++;
				continue;
			}

			$computedHash = \hash( $hashType, $content );
			if ( \hash_equals( $apiHash, $computedHash ) ) {
				$results[] = [ 'locale' => $locale, 'status' => 'PASS', 'reason' => '' ];
				$passed++;
			}
			else {
				$results[] = [
					'locale' => $locale,
					'status' => 'FAIL',
					'reason' => \sprintf( 'API: %s... Computed: %s...',
						\substr( $apiHash, 0, 16 ),
						\substr( $computedHash, 0, 16 )
					),
				];
				$failed++;
			}
		}

		\WP_CLI\Utils\format_items( 'table', $results, [ 'locale', 'status', 'reason' ] );
		\WP_CLI::log( '' );

		if ( $failed === 0 ) {
			\WP_CLI::success( \sprintf( 'All %d locale(s) verified successfully.', $passed ) );
		}
		else {
			\WP_CLI::warning( \sprintf( '%d passed, %d failed, %d skipped.', $passed, $failed, $skipped ) );
		}
	}
}
