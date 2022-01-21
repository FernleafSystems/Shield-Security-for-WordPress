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

	public function forUser( \WP_User $user ) {
		$con = $this->getCon();
		$this->user = $user;

		$meta = null;
		try {
			$meta = Shield\Users\ShieldUserMeta::Load( $con->prefix(), (int)$user->ID );
			$this->loadMetaRecord( $meta );
			$this->setup( $meta );

			Services::WpUsers()->deleteUserMeta( $con->prefix( 'meta-version' ), $user->ID );
		}
		catch ( \Exception $e ) {
		}
		return $meta;
	}

	private function setup( Shield\Users\ShieldUserMeta $meta ) {
		$rec = $meta->record;
		if ( empty( $rec->first_seen_at ) ) {
			$rec->first_seen_at = min( array_filter( [
				Services::Request()->ts(),
				$rec->pass_started_at,
				$rec->last_login_at
			] ) );
		}

		$newHash = substr( sha1( $this->user->user_pass ), 6, 4 );
		if ( !isset( $meta->pass_hash ) || ( $meta->pass_hash !== $newHash ) ) {
			$meta->pass_hash = $newHash;
			$rec->pass_started_at = Services::Request()->ts();
		}
	}

	private function loadMetaRecord( Shield\Users\ShieldUserMeta $meta ) {
		$modData = $this->getCon()->getModule_Data();
		$dbh = $modData->getDbH_UserMeta();

		$metaLoader = ( new Shield\Modules\Data\DB\UserMeta\MetaRecords() )->setMod( $modData );
		$userID = (int)$meta->user_id;

		$metaRecord = $metaLoader->loadMeta( $userID, false );
		if ( empty( $metaRecord ) && $metaLoader->addMeta( $userID ) ) {
			$metaRecord = $metaLoader->loadMeta( $userID );
			$toUpdate = [];

			// Copy old meta to new:
			$directMap = [
				'first_seen_at',
				'last_login_at',
				'hard_suspended_at',
				'pass_started_at',
			];
			foreach ( $directMap as $directMapKey ) {
				$toUpdate[ $directMapKey ] = $meta->{$directMapKey};
				unset( $meta->{$directMapKey} );
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
				if ( !empty( $meta->{$metaKey} ) && empty( $toUpdate[ $profile.'_ready_at' ] ) ) {
					$toUpdate[ $profile.'_ready_at' ] = Services::Request()->ts();
				}
			}

			$dbh->getQueryUpdater()->updateRecord( $metaRecord, $toUpdate );

			$metaRecord = $metaLoader->loadMeta( $userID );
		}

		$meta->record = $metaRecord;
	}
}