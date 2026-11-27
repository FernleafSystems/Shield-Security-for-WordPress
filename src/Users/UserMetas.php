<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\{
	MetaRecords,
	Ops\Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class UserMetas {

	use PluginControllerConsumer;

	/**
	 * How many users' records are fetched by one query when many are loaded at once, here and wherever else
	 * something is held for each user.
	 */
	public const PRELOAD_BATCH = 50;

	public function current() :?ShieldUserMeta {
		return $this->for( Services::WpUsers()->getCurrentWpUser() );
	}

	public function for( ?\WP_User $user ) :?ShieldUserMeta {
		$meta = null;
		if ( !empty( $user ) ) {
			try {
				$meta = ShieldUserMeta::Load( self::con()->prefix(), (int)$user->ID );
				if ( !isset( $meta->record ) ) {
					$this->attachRecord( $meta, ( new MetaRecords() )->loadMeta( (int)$meta->user_id ) );
					$this->setupMeta( $user, $meta );
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $meta;
	}

	/**
	 * Loads the records of many users a batch at a time, so that asking for each of them afterwards costs no
	 * query of its own. It's for looking at people, so a user who has no record isn't given one: they're held as
	 * having none, and a record is stored for them only if something is then written to it.
	 * @param \WP_User[] $users
	 */
	public function preload( array $users ) :void {
		$waiting = [];
		foreach ( $users as $user ) {
			try {
				$meta = ShieldUserMeta::Load( self::con()->prefix(), (int)$user->ID );
				if ( !isset( $meta->record ) ) {
					$waiting[ $user->ID ] = [ $user, $meta ];
				}
			}
			catch ( \Exception $e ) {
			}
		}

		$metaRecords = new MetaRecords();
		foreach ( \array_chunk( $waiting, self::PRELOAD_BATCH, true ) as $batch ) {
			$records = $metaRecords->loadMetas( \array_keys( $batch ) );
			foreach ( $batch as $userID => [ $user, $meta ] ) {
				try {
					if ( isset( $records[ $userID ] ) ) {
						$this->attachRecord( $meta, $records[ $userID ] );
						$this->setupMeta( $user, $meta );
					}
					else {
						$meta->record = $metaRecords->unstoredFor( $userID );
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}
	}

	private function setupMeta( \WP_User $user, ShieldUserMeta $meta ) :void {
		$rec = $meta->record;

		$newHash = \substr( \hash( 'sha1', $user->user_pass ), 6, 4 );
		if ( empty( $rec->pass_started_at ) || !isset( $meta->pass_hash ) || ( $meta->pass_hash !== $newHash ) ) {
			$meta->pass_hash = $newHash;
			$rec->pass_started_at = Services::Request()->ts();
		}

		if ( empty( $rec->first_seen_at ) ) {
			$rec->first_seen_at = \min( \array_filter( [
				Services::Request()->ts(),
				$rec->pass_started_at,
				$rec->last_login_at
			] ) );
		}
	}

	/**
	 * Gives the user's data its record, first bringing the record up to date with anything still held the old way.
	 */
	private function attachRecord( ShieldUserMeta $meta, ?Record $metaRecord ) :void {

		if ( empty( $metaRecord ) ) {
			$metaRecord = self::con()->db_con->user_meta->getRecord();
		}
		else {
			$dataToUpdate = [];

			// Copy old meta to new:
			$directMap = [
				'first_seen_at',
				'last_login_at',
				'hard_suspended_at',
				'pass_started_at',
			];
			foreach ( $directMap as $directMapKey ) {
				if ( !empty( $meta->{$directMapKey} ) && $meta->{$directMapKey} !== $metaRecord->{$directMapKey} ) {
					$dataToUpdate[ $directMapKey ] = $meta->{$directMapKey};
					unset( $meta->{$directMapKey} );
				}
			}

			$mfaProfiles = [
				'backup',
				'email',
				'ga',
				'yubi',
			];
			foreach ( $mfaProfiles as $profile ) {
				$metaKey = $profile.'_validated';
				if ( !empty( $meta->{$metaKey} ) && empty( $metaRecord->{$profile.'_ready_at'} ) ) {
					$dataToUpdate[ $profile.'_ready_at' ] = Services::Request()->ts();
				}
			}

			if ( !empty( $dataToUpdate ) ) {
				self::con()
					->db_con
					->user_meta
					->getQueryUpdater()
					->updateRecord( $metaRecord, $dataToUpdate );
				$metaRecord = ( new MetaRecords() )->loadMeta( (int)$meta->user_id );
			}
		}

		if ( $metaRecord instanceof Record ) {
			$meta->record = $metaRecord;
		}
	}
}