<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record as SiteRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type ProfileSnapshot array{
 *   id:int,
 *   slug:string,
 *   label:string,
 *   is_default:bool,
 *   config:string,
 *   created_at:int,
 *   updated_at:int
 * }
 * @phpstan-type FixtureState array{
 *   options_snapshot:array<string,mixed>,
 *   created_site_id:int,
 *   created_profile_id:int,
 *   existing_profile_snapshot:ProfileSnapshot|null
 * }
 */
class ImportExportNetworkFixtureBuilder {
	private const REQUIRED_DB_KEYS = [
		'import_export_sites',
		'import_export_profiles',
	];

	private const OPTION_KEYS = [
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
		'importexport_enable',
		'importexport_masterurl',
		NetworkInviteRepository::OPTION_KEY,
	];

	/**
	 * @param list<string> $args
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed( array $args = [] ) :array {
		$state = [
			'options_snapshot'          => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'created_site_id'           => 0,
			'created_profile_id'        => 0,
			'existing_profile_snapshot' => null,
		];

		try {
			RuntimeTestState::applyPremiumCapabilities( [ 'import_export_level_1', 'import_export_level_2' ] );
			$con = RuntimeTestState::controller();
			$masterUrl = \in_array( 'connected-master', $args, true ) ? 'https://master.example.com/import' : '';
			$con->opts
				->optSet( 'importexport_enable', 'Y' )
				->optSet( 'importexport_masterurl', $masterUrl )
				->optSet( NetworkInviteRepository::OPTION_KEY, [] )
				->store();

			if ( \in_array( 'profile-client', $args, true ) ) {
				$this->seedProfileClient( $state );
			}

			return [
				'contract' => [],
				'state'    => $state,
			];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		$state = $this->normalizePersistedState( $state );

		try {
			if ( $state[ 'created_site_id' ] > 0
				 || $state[ 'created_profile_id' ] > 0
				 || $state[ 'existing_profile_snapshot' ] !== null ) {
				RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );
			}

			if ( $state[ 'created_site_id' ] > 0 ) {
				$siteRepo = new SiteRepository();
				$siteRepo->deleteByIds( [ $state[ 'created_site_id' ] ] );
				if ( $siteRepo->findById( $state[ 'created_site_id' ], true ) instanceof SiteRecord ) {
					throw new \RuntimeException( 'Unable to delete the import/export profile client fixture.' );
				}
			}

			if ( $state[ 'created_profile_id' ] > 0 ) {
				$profileRepo = new ProfileRepository();
				$profile = $profileRepo->findById( $state[ 'created_profile_id' ] );
				if ( $profile instanceof ProfileRecord ) {
					RuntimeTestState::controller()->db_con->import_export_profiles
						->getQueryDeleter()
						->deleteById( $profile->id );
					if ( $profileRepo->findById( $profile->id ) instanceof ProfileRecord ) {
						throw new \RuntimeException( 'Unable to delete the import/export profile fixture.' );
					}
				}
			}
			elseif ( $state[ 'existing_profile_snapshot' ] !== null ) {
				$this->restoreProfileSnapshot( $state[ 'existing_profile_snapshot' ] );
			}
		}
		finally {
			RuntimeTestState::restoreOptions( $state[ 'options_snapshot' ] );
		}
	}

	/**
	 * @param array<string,mixed> $state
	 * @phpstan-return FixtureState
	 */
	private function normalizePersistedState( array $state ) :array {
		$snapshot = \is_array( $state[ 'existing_profile_snapshot' ] ?? null )
			? $state[ 'existing_profile_snapshot' ]
			: [];
		$snapshotID = (int)( $snapshot[ 'id' ] ?? 0 );

		return [
			'options_snapshot'          => \is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : [],
			'created_site_id'           => \max( 0, (int)( $state[ 'created_site_id' ] ?? 0 ) ),
			'created_profile_id'        => \max( 0, (int)( $state[ 'created_profile_id' ] ?? 0 ) ),
			'existing_profile_snapshot' => $snapshotID > 0 ? [
				'id'         => $snapshotID,
				'slug'       => (string)( $snapshot[ 'slug' ] ?? '' ),
				'label'      => (string)( $snapshot[ 'label' ] ?? '' ),
				'is_default' => (bool)( $snapshot[ 'is_default' ] ?? false ),
				'config'     => (string)( $snapshot[ 'config' ] ?? '' ),
				'created_at' => (int)( $snapshot[ 'created_at' ] ?? 0 ),
				'updated_at' => (int)( $snapshot[ 'updated_at' ] ?? 0 ),
			] : null,
		];
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	private function seedProfileClient( array &$state ) :void {
		RuntimeTestState::ensureDb( self::REQUIRED_DB_KEYS );
		$profileRepo = new ProfileRepository();
		$siteRepo = new SiteRepository();
		$existingProfile = $profileRepo->findBySlug( ProfileRepository::DEFAULT_SLUG );
		if ( $existingProfile instanceof ProfileRecord ) {
			$state[ 'existing_profile_snapshot' ] = $this->profileSnapshot( $existingProfile );
		}

		$url = \sprintf(
			'https://profile-client-%s.example.com/',
			\str_replace( '-', '', \wp_generate_uuid4() )
		);
		if ( $siteRepo->findByUrl( $url, true ) instanceof SiteRecord ) {
			throw new \RuntimeException( 'Import/export profile client fixture URL already exists.' );
		}

		$site = $siteRepo->upsertActive( $url, SitesDB::SOURCE_MANUAL );
		$profile = $profileRepo->findBySlug( ProfileRepository::DEFAULT_SLUG );
		if ( $site instanceof SiteRecord && $site->id > 0 ) {
			$state[ 'created_site_id' ] = $site->id;
		}
		if ( !( $existingProfile instanceof ProfileRecord ) ) {
			if ( $site instanceof SiteRecord && $site->profile_ref > 0 ) {
				$state[ 'created_profile_id' ] = $site->profile_ref;
			}
			elseif ( $profile instanceof ProfileRecord ) {
				$state[ 'created_profile_id' ] = $profile->id;
			}
		}

		if ( !( $site instanceof SiteRecord )
			 || $site->id < 1
			 || $site->status !== SitesDB::STATUS_ACTIVE
			 || !( $profile instanceof ProfileRecord )
			 || $profile->id < 1
			 || $site->profile_ref !== $profile->id ) {
			throw new \RuntimeException( 'Unable to create the import/export profile client fixture.' );
		}
		if ( $existingProfile instanceof ProfileRecord && $profile->id !== $existingProfile->id ) {
			throw new \RuntimeException( 'Import/export profile client fixture changed the default profile identity.' );
		}
	}

	/**
	 * @phpstan-return ProfileSnapshot
	 */
	private function profileSnapshot( ProfileRecord $profile ) :array {
		return [
			'id'         => $profile->id,
			'slug'       => $profile->slug,
			'label'      => $profile->label,
			'is_default' => $profile->is_default,
			'config'     => $profile->config,
			'created_at' => $profile->created_at,
			'updated_at' => $profile->updated_at,
		];
	}

	/**
	 * @phpstan-param ProfileSnapshot $snapshot
	 */
	private function restoreProfileSnapshot( array $snapshot ) :void {
		$profile = ( new ProfileRepository() )->findById( $snapshot[ 'id' ] );
		if ( !( $profile instanceof ProfileRecord ) ) {
			throw new \RuntimeException( 'Pre-existing import/export profile disappeared during fixture cleanup.' );
		}

		$current = $this->profileSnapshot( $profile );
		if ( $current === $snapshot ) {
			return;
		}

		$updated = RuntimeTestState::controller()->db_con->import_export_profiles
			->getQueryUpdater()
			->updateById( $snapshot[ 'id' ], [
				'slug'       => $snapshot[ 'slug' ],
				'label'      => $snapshot[ 'label' ],
				'is_default' => $snapshot[ 'is_default' ] ? 1 : 0,
				'config'     => $snapshot[ 'config' ],
				'created_at' => $snapshot[ 'created_at' ],
				'updated_at' => $snapshot[ 'updated_at' ],
			] );
		if ( !$updated ) {
			throw new \RuntimeException( 'Unable to restore the pre-existing import/export profile.' );
		}
	}
}
