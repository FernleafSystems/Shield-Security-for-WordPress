<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PreSetOptSanitize;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\{
	Handler as ProfilesDB,
	Record as ProfileRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record as SiteRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ProfileRepository {

	use PluginControllerConsumer;

	public const DEFAULT_SLUG = 'default';
	public const DEFAULT_LABEL = 'Default Profile';
	public const UNKNOWN_LABEL = 'Unknown Profile';
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

	public function ensureDefaultProfile() :?ProfileRecord {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() ) {
			return null;
		}

		$profile = $this->findDefaultProfile();
		if ( !( $profile instanceof ProfileRecord ) ) {
			$profile = $this->findBySlug( self::DEFAULT_SLUG );
			if ( $profile instanceof ProfileRecord ) {
				$this->markProfileAsDefault( $profile );
			}
		}

		if ( !( $profile instanceof ProfileRecord ) ) {
			$profile = $this->createDefaultProfile();
		}

		if ( $profile instanceof ProfileRecord ) {
			$this->ensureDefaultProfileLabel( $profile );
			$this->normaliseDefaultProfileFlags( $profile->id );
			$this->repairMissingSiteProfileRefs( $profile->id );
		}

		return $profile;
	}

	public function defaultProfile() :?ProfileRecord {
		return $this->ensureDefaultProfile();
	}

	public function profileForSite( ?SiteRecord $site ) :?ProfileRecord {
		if ( $site instanceof SiteRecord && $site->profile_ref > 0 ) {
			$profile = $this->findById( $site->profile_ref );
			if ( $profile instanceof ProfileRecord ) {
				return $profile;
			}
		}

		$profile = $this->ensureDefaultProfile();
		if ( $profile instanceof ProfileRecord && $site instanceof SiteRecord ) {
			$this->assignDefaultProfileToSite( $site, $profile->id );
		}
		return $profile;
	}

	public function resolveProfileRefForSite( ?SiteRecord $site ) :int {
		if ( $site instanceof SiteRecord && $site->profile_ref > 0 ) {
			if ( $this->findById( $site->profile_ref ) instanceof ProfileRecord ) {
				return $site->profile_ref;
			}
		}

		$profile = $this->ensureDefaultProfile();
		if ( !( $profile instanceof ProfileRecord ) ) {
			return 0;
		}
		if ( $site instanceof SiteRecord ) {
			$this->assignDefaultProfileToSite( $site, $profile->id );
		}
		return $profile->id;
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
	 * @param SiteRecord[] $sites
	 * @return array<int,string>
	 */
	public function profileLabelsForSites( array $sites ) :array {
		$sites = \array_values( \array_filter(
			$sites,
			static fn( $site ) :bool => $site instanceof SiteRecord
		) );
		if ( empty( $sites ) ) {
			return [];
		}

		$labels = $this->labelsById( \array_map(
			static fn( SiteRecord $site ) :int => (int)$site->profile_ref,
			$sites
		) );

		$defaultProfile = null;
		foreach ( $sites as $site ) {
			if ( $site->profile_ref <= 0 || !isset( $labels[ $site->profile_ref ] ) ) {
				$defaultProfile = $defaultProfile ?? $this->ensureDefaultProfile();
				if ( $defaultProfile instanceof ProfileRecord ) {
					$this->assignDefaultProfileToSite( $site, $defaultProfile->id );
					$site->profile_ref = $defaultProfile->id;
					$labels[ $defaultProfile->id ] = $this->labelForProfile( $defaultProfile );
				}
				else {
					$labels[ $site->profile_ref ] = $site->profile_ref > 0 ? self::UNKNOWN_LABEL : self::DEFAULT_LABEL;
				}
			}
		}

		return $labels;
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
				$labels[ $row->id ] = $this->labelForProfile( $row );
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
		return $this->setOptionsIncluded( $profile, [ $optionKey ], $included );
	}

	/**
	 * @param string[] $optionKeys
	 */
	public function setOptionsIncluded( ProfileRecord $profile, array $optionKeys, bool $included ) :bool {
		$profileableKeys = ( new ProfileOptionsCatalog() )->profileableKeys();
		$optionKeys = \array_values( \array_intersect(
			\array_unique( \array_map( '\strval', $optionKeys ) ),
			$profileableKeys
		) );
		if ( empty( $optionKeys ) ) {
			return false;
		}

		$config = $this->configForProfile( $profile );
		$excluded = \array_diff( $config[ 'excluded' ], $optionKeys );
		if ( !$included ) {
			$excluded = \array_merge( $excluded, $optionKeys );
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

	public function copyCurrentSiteConfigToDefaultProfile() :bool {
		$profile = $this->ensureDefaultProfile();
		if ( !( $profile instanceof ProfileRecord ) ) {
			return false;
		}

		$config = $this->configForProfile( $profile );
		$config[ 'options' ] = $this->currentSiteOptionValues();
		return $this->saveConfig( $profile, $config );
	}

	public function repairMissingSiteProfileRefs( int $profileID ) :int {
		if ( $profileID <= 0 ) {
			return 0;
		}

		try {
			$sitesDbh = self::con()->db_con->import_export_sites;
			$profilesDbh = $this->dbOrNull();
			if ( !$sitesDbh->isReady() || !( $profilesDbh instanceof ProfilesDB ) || !$profilesDbh->isReady() ) {
				return 0;
			}

			global $wpdb;
			$affected = Services::WpDb()->doSql( $wpdb->prepare(
				sprintf(
					'UPDATE `%1$s` AS `sites`
					 LEFT JOIN `%2$s` AS `profiles` ON `profiles`.`id`=`sites`.`profile_ref`
					 SET `sites`.`profile_ref`=%%d, `sites`.`updated_at`=%%d
					 WHERE `sites`.`profile_ref`=0 OR `profiles`.`id` IS NULL;',
					$sitesDbh->getTable(),
					$profilesDbh->getTable()
				),
				$profileID,
				Services::Request()->ts()
			) );
			return \is_numeric( $affected ) ? (int)$affected : 0;
		}
		catch ( \Throwable $e ) {
			return 0;
		}
	}

	private function findDefaultProfile() :?ProfileRecord {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() ) {
			return null;
		}

		return $dbh
			->getQuerySelector()
			->addWhereEquals( 'is_default', 1 )
			->addWhereEquals( 'slug', self::DEFAULT_SLUG )
			->setOrderBy( 'id', 'ASC' )
			->first();
	}

	private function createDefaultProfile() :?ProfileRecord {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() ) {
			return null;
		}

		$now = Services::Request()->ts();
		$record = $dbh->getRecord();
		$record->slug = self::DEFAULT_SLUG;
		$record->label = self::DEFAULT_LABEL;
		$record->is_default = true;
		$record->config = $this->encodeConfig( $this->normaliseConfig( $this->initialConfigFromCurrentSite() ) );
		$record->created_at = $now;
		$record->updated_at = $now;

		$dbh->getQueryInserter()->insert( $record );
		return $this->findBySlug( self::DEFAULT_SLUG );
	}

	private function markProfileAsDefault( ProfileRecord $profile ) :void {
		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() || $profile->id <= 0 || $profile->is_default ) {
			return;
		}

		$updatedAt = Services::Request()->ts();
		if ( $dbh->getQueryUpdater()->updateById( $profile->id, [
			'is_default' => 1,
			'updated_at' => $updatedAt,
		] ) ) {
			$profile->is_default = true;
			$profile->updated_at = $updatedAt;
		}
	}

	private function ensureDefaultProfileLabel( ProfileRecord $profile ) :void {
		if ( $profile->label !== '' ) {
			return;
		}

		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() || $profile->id <= 0 ) {
			return;
		}

		$updatedAt = Services::Request()->ts();
		if ( $dbh->getQueryUpdater()->updateById( $profile->id, [
			'label'      => self::DEFAULT_LABEL,
			'updated_at' => $updatedAt,
		] ) ) {
			$profile->label = self::DEFAULT_LABEL;
			$profile->updated_at = $updatedAt;
		}
	}

	private function normaliseDefaultProfileFlags( int $profileID ) :void {
		if ( $profileID <= 0 ) {
			return;
		}

		$dbh = $this->dbOrNull();
		if ( !( $dbh instanceof ProfilesDB ) || !$dbh->isReady() ) {
			return;
		}

		try {
			global $wpdb;
			Services::WpDb()->doSql( $wpdb->prepare(
				sprintf(
					'UPDATE `%s` SET `is_default`=0, `updated_at`=%%d WHERE `id`<>%%d AND `is_default`=1;',
					$dbh->getTable()
				),
				Services::Request()->ts(),
				$profileID
			) );
		}
		catch ( \Throwable $e ) {
		}
	}

	private function assignDefaultProfileToSite( SiteRecord $site, int $profileID ) :void {
		if ( $profileID <= 0 || $site->id <= 0 || $site->profile_ref === $profileID ) {
			return;
		}

		try {
			$dbh = self::con()->db_con->import_export_sites;
			if ( !( $dbh instanceof SitesDB ) || !$dbh->isReady() ) {
				return;
			}

			$updatedAt = Services::Request()->ts();
			if ( $dbh->getQueryUpdater()->updateById( $site->id, [
				'profile_ref' => $profileID,
				'updated_at'  => $updatedAt,
			] ) ) {
				$site->profile_ref = $profileID;
				$site->updated_at = $updatedAt;
			}
		}
		catch ( \Throwable $e ) {
		}
	}

	private function labelForProfile( ProfileRecord $profile ) :string {
		return $profile->label === '' ? self::DEFAULT_LABEL : $profile->label;
	}

	/**
	 * @return array{schema_version:int,options:array<string,mixed>,excluded:string[]}
	 */
	private function initialConfigFromCurrentSite() :array {
		$options = $this->currentSiteOptionValues();

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
	 * @return array<string,mixed>
	 */
	private function currentSiteOptionValues() :array {
		$options = [];
		foreach ( ( new ProfileOptionsCatalog() )->profileableKeys() as $key ) {
			$options[ $key ] = self::con()->opts->optGet( $key );
		}
		return $options;
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
