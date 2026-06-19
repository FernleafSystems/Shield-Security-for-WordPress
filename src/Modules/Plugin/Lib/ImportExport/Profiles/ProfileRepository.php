<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreSetOptSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\{
	Handler as ProfilesDB,
	Record as ProfileRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Record as SiteRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ProfileRepository {

	use PluginControllerConsumer;

	public const PRIMARY_SLUG = 'primary';
	public const PRIMARY_LABEL = 'Primary Profile';
	public const CONFIG_SCHEMA_VERSION = 1;

	/**
	 * @return array{schema_version:int,options:array<string,mixed>,excluded:string[]}
	 */
	public function emptyConfig() :array {
		return [
			'schema_version' => self::CONFIG_SCHEMA_VERSION,
			'options'        => [],
			'excluded'       => [],
		];
	}

	public function ensurePrimaryProfile() :?ProfileRecord {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() ) {
			return null;
		}

		$profile = $this->findBySlug( self::PRIMARY_SLUG );
		if ( $profile instanceof ProfileRecord ) {
			$this->ensureSitesAssignedToPrimary( $profile->id );
			return $profile;
		}

		$now = Services::Request()->ts();
		$record = $dbh->getRecord();
		$record->slug = self::PRIMARY_SLUG;
		$record->label = self::PRIMARY_LABEL;
		$record->config = $this->encodeConfig( $this->normaliseConfig( $this->initialConfigFromCurrentSite() ) );
		$record->created_at = $now;
		$record->updated_at = $now;

		$dbh->getQueryInserter()->insert( $record );
		$profile = $this->findBySlug( self::PRIMARY_SLUG );
		if ( $profile instanceof ProfileRecord ) {
			$this->ensureSitesAssignedToPrimary( $profile->id );
		}
		return $profile;
	}

	public function primaryProfile() :?ProfileRecord {
		return $this->ensurePrimaryProfile();
	}

	public function profileForSite( ?SiteRecord $site ) :?ProfileRecord {
		$profile = ( $site instanceof SiteRecord && $site->profile_ref > 0 )
			? $this->findById( $site->profile_ref )
			: null;
		return $profile instanceof ProfileRecord ? $profile : $this->primaryProfile();
	}

	public function findById( int $id ) :?ProfileRecord {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() || $id <= 0 ) {
			return null;
		}

		return $dbh
			->getQuerySelector()
			->addWhereEquals( 'id', $id )
			->first();
	}

	public function findBySlug( string $slug ) :?ProfileRecord {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() || $slug === '' ) {
			return null;
		}

		return $dbh
			->getQuerySelector()
			->addWhereEquals( 'slug', $slug )
			->first();
	}

	/**
	 * @return array<int,string>
	 */
	public function labelsById( array $ids ) :array {
		$ids = \array_values( \array_unique( \array_filter( \array_map( '\intval', $ids ), static fn( int $id ) :bool => $id > 0 ) ) );
		if ( empty( $ids ) ) {
			return [];
		}

		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() ) {
			return [];
		}

		$labels = [];
		$rows = $dbh
			->getQuerySelector()
			->addWhereIn( 'id', $ids )
			->queryWithResult() ?? [];
		foreach ( $rows as $row ) {
			if ( $row instanceof ProfileRecord ) {
				$labels[ $row->id ] = $row->label === '' ? self::PRIMARY_LABEL : $row->label;
			}
		}
		return $labels;
	}

	/**
	 * @return array{schema_version:int,options:array<string,mixed>,excluded:string[]}
	 */
	public function configForProfile( ProfileRecord $profile ) :array {
		return $this->normaliseConfig( $this->decodeConfig( $profile->config ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function exportOptionsForProfile( ProfileRecord $profile ) :array {
		$config = $this->configForProfile( $profile );
		return \array_diff_key( $config[ 'options' ], \array_flip( $config[ 'excluded' ] ) );
	}

	/**
	 * @param array{schema_version:int,options:array<string,mixed>,excluded:string[]} $config
	 */
	public function saveConfig( ProfileRecord $profile, array $config ) :bool {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() || $profile->id <= 0 ) {
			return false;
		}

		$encoded = $this->encodeConfig( $this->normaliseConfig( $config ) );
		$updatedAt = Services::Request()->ts();
		$success = $dbh->getQueryUpdater()->updateById( $profile->id, [
			'config'     => $encoded,
			'updated_at' => $updatedAt,
		] );
		if ( $success ) {
			$profile->config = $encoded;
			$profile->updated_at = $updatedAt;
		}
		return $success;
	}

	public function setOptionIncluded( ProfileRecord $profile, string $optionKey, bool $included ) :bool {
		$profileableKeys = ( new ProfileOptionsCatalog() )->profileableKeys();
		if ( !\in_array( $optionKey, $profileableKeys, true ) ) {
			return false;
		}

		$config = $this->configForProfile( $profile );
		$excluded = \array_diff( $config[ 'excluded' ], [ $optionKey ] );
		if ( !$included ) {
			$excluded[] = $optionKey;
		}
		$config[ 'excluded' ] = \array_values( \array_unique( $excluded ) );
		return $this->saveConfig( $profile, $config );
	}

	/**
	 * @param array<string,mixed> $values
	 */
	public function saveOptionValues( ProfileRecord $profile, array $values ) :bool {
		$config = $this->configForProfile( $profile );
		$profileableKeys = ( new ProfileOptionsCatalog() )->profileableKeys();
		foreach ( $values as $key => $value ) {
			if ( \in_array( $key, $profileableKeys, true ) ) {
				try {
					$config[ 'options' ][ $key ] = ( new PreSetOptSanitize( $key, $value ) )->run();
				}
				catch ( \Throwable $e ) {
				}
			}
		}

		return $this->saveConfig( $profile, $config );
	}

	private function ensureSitesAssignedToPrimary( int $profileID ) :void {
		if ( $profileID <= 0 ) {
			return;
		}

		try {
			$dbh = self::con()->db_con->import_export_sites;
			if ( !$dbh->isReady() ) {
				return;
			}
			global $wpdb;
			Services::WpDb()->doSql( $wpdb->prepare(
				sprintf( 'UPDATE `%s` SET `profile_ref`=%%d WHERE `profile_ref`=0;', $dbh->getTable() ),
				$profileID
			) );
		}
		catch ( \Throwable $e ) {
		}
	}

	/**
	 * @return array{schema_version:int,options:array<string,mixed>,excluded:string[]}
	 */
	private function initialConfigFromCurrentSite() :array {
		$options = [];
		foreach ( ( new ProfileOptionsCatalog() )->profileableKeys() as $key ) {
			$options[ $key ] = self::con()->opts->optGet( $key );
		}

		$xferExcluded = self::con()->opts->optGet( 'xfer_excluded' );
		$excluded = \array_values( \array_intersect(
			\is_array( $xferExcluded ) ? $xferExcluded : [],
			\array_keys( $options )
		) );

		return [
			'schema_version' => self::CONFIG_SCHEMA_VERSION,
			'options'        => $options,
			'excluded'       => $excluded,
		];
	}

	/**
	 * @return array{schema_version:int,options:array<string,mixed>,excluded:string[]}
	 */
	private function normaliseConfig( array $config ) :array {
		$profileable = ( new ProfileOptionsCatalog() )->profileableKeys();
		$options = \is_array( $config[ 'options' ] ?? null ) ? $config[ 'options' ] : [];
		$normalisedOptions = [];
		foreach ( $profileable as $key ) {
			$value = \array_key_exists( $key, $options ) ? $options[ $key ] : self::con()->opts->optGet( $key );
			try {
				$normalisedOptions[ $key ] = ( new PreSetOptSanitize( $key, $value ) )->run();
			}
			catch ( \Throwable $e ) {
				$normalisedOptions[ $key ] = self::con()->opts->optGet( $key );
			}
		}

		$excluded = \is_array( $config[ 'excluded' ] ?? null ) ? $config[ 'excluded' ] : [];
		$excluded = \array_values( \array_intersect( \array_map( '\strval', $excluded ), $profileable ) );

		return [
			'schema_version' => self::CONFIG_SCHEMA_VERSION,
			'options'        => $normalisedOptions,
			'excluded'       => $excluded,
		];
	}

	private function encodeConfig( array $config ) :string {
		return (string)\wp_json_encode( $config );
	}

	private function decodeConfig( string $encoded ) :array {
		$config = @\json_decode( $encoded, true );
		return \is_array( $config ) ? $config : $this->emptyConfig();
	}

	private function dbOrNull() :?ProfilesDB {
		try {
			return self::con()->db_con->import_export_profiles;
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}
}
