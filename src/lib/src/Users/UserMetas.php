<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Users;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class UserMetas {

	use Shield\Modules\PluginControllerConsumer;

	/**
	 * @var \WP_User
	 */
	private $user;

	public function current() :?ShieldUserMeta {
		return $this->for( Services::WpUsers()->getCurrentWpUser() );
	}

	public function for( ?\WP_User $user ) :?ShieldUserMeta {
		$meta = null;
		if ( $user instanceof \WP_User ) {
			$this->user = $user;
			try {
				$meta = Shield\Users\ShieldUserMeta::Load( self::con()->prefix(), (int)$user->ID );
				if ( !isset( $meta->record ) ) {
					$this->loadMetaRecord( $meta );
					$this->setup( $meta );
					// TODO: a query to delete all of these
					Services::WpUsers()->deleteUserMeta( self::con()->prefix( 'meta-version' ), $user->ID );
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $meta;
	}

	private function setup( Shield\Users\ShieldUserMeta $meta ) {
		$rec = $meta->record;

		$newHash = \substr( \sha1( $this->user->user_pass ), 6, 4 );
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

	private function loadMetaRecord( Shield\Users\ShieldUserMeta $meta ) {

		$metaLoader = new Shield\Modules\Data\DB\UserMeta\MetaRecords();
		$metaRecord = $metaLoader->loadMeta( (int)$meta->user_id );

		if ( empty( $metaRecord ) ) {
			$metaRecord = self::con()->getModule_Data()->getDbH_UserMeta()->getRecord();
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
				'u2f',
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
					->getModule_Data()
					->getDbH_UserMeta()
					->getQueryUpdater()
					->updateRecord( $metaRecord, $dataToUpdate );
				$metaRecord = $metaLoader->loadMeta( (int)$meta->user_id );
			}
		}

		$meta->record = $metaRecord;
	}
}