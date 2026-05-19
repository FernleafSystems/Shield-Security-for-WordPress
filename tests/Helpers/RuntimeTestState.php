<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogger;
use FernleafSystems\Wordpress\Services\Services;
use WP_User;

class RuntimeTestState {

	public static function controller() :Controller {
		$plugin = \function_exists( 'shield_security_get_plugin' )
			? \shield_security_get_plugin()
			: null;
		$controller = \is_object( $plugin ) && \method_exists( $plugin, 'getController' )
			? $plugin->getController()
			: null;
		if ( !$controller instanceof Controller ) {
			throw new \RuntimeException( 'Shield Controller is not available.' );
		}

		return $controller;
	}

	/**
	 * @param list<string> $dbKeys
	 */
	public static function ensureDb( array $dbKeys ) :void {
		$con = self::controller();
		$con->db_con->loadAll();

		foreach ( $dbKeys as $dbKey ) {
			self::requireDbHandler( $dbKey );
		}
	}

	/**
	 * Optional handlers such as file_locker may need a forced reload after
	 * feature storage is enabled so their tables are created in the current runtime.
	 *
	 * @return mixed
	 */
	public static function requireDbHandler( string $dbKey, bool $reload = false ) {
		$con = self::controller();
		$handler = self::loadDbHandler( $dbKey, $reload );

		if ( empty( $handler ) ) {
			throw new \RuntimeException( \sprintf( 'DB handler "%s" could not be loaded.', $dbKey ) );
		}

		if ( !$handler->tableExists() ) {
			Services::WpDb()->doSql( $handler->getTableSchema()->buildCreate() );
			Services::WpDb()->clearResultShowTables();
			$handler = self::loadDbHandler( $dbKey, true );
		}

		if ( empty( $handler ) || !$handler->isReady() || !$handler->tableExists() ) {
			throw new \RuntimeException( \sprintf( 'DB handler "%s" is not ready.', $dbKey ) );
		}

		return $handler;
	}

	/**
	 * @return mixed
	 */
	private static function loadDbHandler( string $dbKey, bool $reload ) {
		$con = self::controller();
		return $reload
			? $con->db_con->loadDbH( $con->db_con::MAP[ $dbKey ][ 'slug' ], true )
			: $con->db_con->load( $dbKey );
	}

	public static function loginAsSecurityAdmin( string $login = 'admin' ) :int {
		$user = \get_user_by( 'login', $login );
		if ( !$user instanceof WP_User ) {
			throw new \RuntimeException( \sprintf( 'Administrator user "%s" is not available.', $login ) );
		}

		\wp_set_current_user( (int)$user->ID );
		self::controller()->this_req->is_security_admin = true;
		return (int)$user->ID;
	}

	/**
	 * @param list<string> $keys
	 * @return array<string,mixed>
	 */
	public static function snapshotOptions( array $keys ) :array {
		$opts = self::controller()->opts;
		$snapshot = [];

		foreach ( $keys as $key ) {
			$snapshot[ $key ] = $opts->optGet( $key );
		}

		return $snapshot;
	}

	/**
	 * @param array<string,mixed> $snapshot
	 */
	public static function restoreOptions( array $snapshot, bool $store = true ) :void {
		$con = self::controller();
		foreach ( $snapshot as $key => $value ) {
			if ( $key === 'license_data' ) {
				$con->comps->license->updateLicenseData( \is_array( $value ) ? $value : [] );
				continue;
			}
			$con->opts->optSet( (string)$key, $value );
		}

		if ( $store ) {
			self::persistAndRefreshOptions( $snapshot );
			return;
		}
		self::refreshOptionsRuntimeState();
	}

	/**
	 * @param list<string> $capabilities
	 */
	public static function applyPremiumCapabilities( array $capabilities ) :void {
		$con = self::controller();
		$ts = \time();
		$licenseData = [
			'checksum'         => 'runtime-test-license',
			'success'          => true,
			'license'          => 'valid',
			'expires'          => 'lifetime',
			'last_request_at'  => $ts,
			'last_verified_at' => $ts,
			'capabilities'     => \array_values( \array_unique( \array_filter(
				$capabilities,
				fn( $cap ) => \is_string( $cap ) && $cap !== ''
			) ) ),
			'lic_version'      => 1,
		];

		$con->comps->license->updateLicenseData( $licenseData );

		$con->opts
			->optSet( 'license_activated_at', $ts )
			->optSet( 'license_deactivated_at', 0 );
		self::persistAndRefreshOptions( [
			'license_data'           => $licenseData,
			'license_activated_at'   => $ts,
			'license_deactivated_at' => 0,
		] );
	}

	public static function disablePremiumCapabilities() :void {
		$con = self::controller();
		$con->comps->license->updateLicenseData( [] );
		$con->opts
			->optSet( 'license_activated_at', 0 )
			->optSet( 'license_deactivated_at', 0 );
		self::persistAndRefreshOptions( [
			'license_data'           => [],
			'license_activated_at'   => 0,
			'license_deactivated_at' => 0,
		] );
	}

	public static function primeShieldNetHandshake() :void {
		$con = self::controller();
		$shieldNet = $con->comps->shieldnet;
		$ts = \time();
		$vo = $shieldNet->vo;
		$vo->last_handshake_at = $ts;
		$vo->last_handshake_attempt_at = $ts;
		$vo->handshake_fail_count = 0;
		$vo->data_last_saved_at = $ts;
		$shieldNet->storeVoData();
		self::forcePersistOptions( [
			'snapi_data' => $con->opts->optGet( 'snapi_data' ),
		] );
	}

	public static function resetScanResultCountMemoization() :void {
		self::controller()->comps->scans->resetScanResultsCountMemoization();
	}

	public static function clearFileLocks() :void {
		self::controller()->comps->file_locker->clearLocks();
	}

	public static function primeCacheSubDir( string $slug ) :void {
		self::controller()->cache_dir_handler->buildSubDir( $slug );
	}

	/**
	 * @param array<string,mixed> $updates
	 */
	public static function forcePersistOptions( array $updates ) :void {
		if ( $updates === [] ) {
			return;
		}

		$con = self::controller();
		$optionKey = $con->prefix( 'opts_all', '_' );
		$stored = \get_option( $optionKey, [] );
		$stored = \is_array( $stored ) ? $stored : [];
		$stored = \array_merge( [
			'values' => [
				OptsHandler::TYPE_FREE => [],
				OptsHandler::TYPE_PRO  => [],
			],
		], $stored );
		$stored[ 'values' ][ OptsHandler::TYPE_FREE ] = \is_array( $stored[ 'values' ][ OptsHandler::TYPE_FREE ] ?? null )
			? $stored[ 'values' ][ OptsHandler::TYPE_FREE ]
			: [];
		$stored[ 'values' ][ OptsHandler::TYPE_PRO ] = \is_array( $stored[ 'values' ][ OptsHandler::TYPE_PRO ] ?? null )
			? $stored[ 'values' ][ OptsHandler::TYPE_PRO ]
			: [];

		foreach ( $updates as $key => $value ) {
			$key = (string)$key;
			$optionDef = $con->cfg->configuration->options[ $key ] ?? [];
			if ( ( $optionDef[ 'premium' ] ?? false ) === true ) {
				$default = $optionDef[ 'default' ] ?? null;
				$stored[ 'values' ][ OptsHandler::TYPE_FREE ][ $key ] = $default;
				if ( \serialize( $value ) === \serialize( $default ) ) {
					unset( $stored[ 'values' ][ OptsHandler::TYPE_PRO ][ $key ] );
				}
				else {
					$stored[ 'values' ][ OptsHandler::TYPE_PRO ][ $key ] = $value;
				}
				continue;
			}

			$stored[ 'values' ][ OptsHandler::TYPE_FREE ][ $key ] = $value;
			unset( $stored[ 'values' ][ OptsHandler::TYPE_PRO ][ $key ] );
		}

		\update_option( $optionKey, $stored, false );
	}

	public static function resetOptionsRuntimeCache() :void {
		$opts = self::controller()->opts;
		$reflection = new \ReflectionClass( OptsHandler::class );

		foreach ( [
			'values'           => null,
			'merged'           => false,
			'startedAsPremium' => false,
		] as $propertyName => $value ) {
			if ( !$reflection->hasProperty( $propertyName ) ) {
				continue;
			}

			$property = $reflection->getProperty( $propertyName );
			$property->setAccessible( true );
			$property->setValue( $opts, $value );
		}

		unset( $opts->mod_opts_all, $opts->mod_opts_free, $opts->mod_opts_pro );
	}

	public static function resetMfaProviderCache() :void {
		$mfa = self::controller()->comps->mfa ?? null;
		if ( !\is_object( $mfa ) ) {
			return;
		}

		$reflection = new \ReflectionClass( $mfa );
		if ( $reflection->hasProperty( 'providers' ) ) {
			$property = $reflection->getProperty( 'providers' );
			$property->setAccessible( true );
			$property->setValue( $mfa, [] );
		}
	}

	public static function resetRequestLoggerState() :void {
		$logger = self::controller()->comps->requests_log ?? null;
		if ( !$logger instanceof RequestLogger ) {
			return;
		}

		\Closure::bind( function () {
			$this->hasLogged = false;
			$this->isDependentLog = false;
			$this->lastLoggedRecord = null;
			unset( $this->logger );
		}, $logger, RequestLogger::class )();
	}

	/**
	 * @param array<string,mixed> $updates
	 */
	private static function persistAndRefreshOptions( array $updates ) :void {
		self::forcePersistOptions( $updates );
		self::refreshOptionsRuntimeState();
	}

	private static function refreshOptionsRuntimeState() :void {
		self::clearOptionsDirtyState();
		self::resetOptionsRuntimeCache();
	}

	private static function clearOptionsDirtyState() :void {
		$opts = self::controller()->opts;
		$reflection = new \ReflectionClass( OptsHandler::class );
		if ( !$reflection->hasProperty( 'changes' ) ) {
			return;
		}

		$property = $reflection->getProperty( 'changes' );
		$property->setAccessible( true );
		$property->setValue( $opts, [] );
	}
}
