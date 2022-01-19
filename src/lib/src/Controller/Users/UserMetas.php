<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Users;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class UserMetas {

	use Shield\Modules\PluginControllerConsumer;

	public function forUser( \WP_User $user ) {
		$con = $this->getCon();

		$meta = null;
		try {
			/** @var Shield\Users\ShieldUserMeta $meta */
			$meta = Shield\Users\ShieldUserMeta::Load( $con->prefix(), $user->ID );
			if ( !$meta instanceof Shield\Users\ShieldUserMeta ) {
				// Weird: user reported an error where it wasn't of the correct type
				$meta = new Shield\Users\ShieldUserMeta( $con->prefix(), $user->ID );
				Shield\Users\ShieldUserMeta::AddToCache( $meta );
			}

			$meta->setPasswordStartedAt( $user->user_pass )
				 ->updateFirstSeenAt();

			$this->applyUserMetaRecord( $meta);

			Services::WpUsers()
					->updateUserMeta( $con->prefix( 'meta-version' ), $con->getVersionNumeric(), $user->ID );
		}
		catch ( \Exception $e ) {
		}
		return $meta;
	}

	private function applyUserMetaRecord( Shield\Users\ShieldUserMeta $meta ) {
		$modData = $this->getCon()->getModule_Data();
		$dbh = $modData->getDbH_UserMeta();

		$metaLoader = ( new Shield\Modules\Data\DB\UserMeta\MetaRecords() )->setMod( $modData );
		$userID = (int)$meta->user_id;

		$metaRecord = $metaLoader->loadMeta( $userID, false );
		if ( empty( $metaRecord ) && $metaLoader->addMeta( $userID ) ) {
			$metaRecord = $metaLoader->loadMeta( $userID );
			$updateData = [];
			// Copy old meta to new:
			$directMap = [
				'first_seen_at',
				'last_login_at',
				'hard_suspended_at',
				'soft_suspended_at',
				'pass_started_at',
				'pass_check_failed_at',
				'pass_reset_last_redirect_at',
			];

			foreach ( $directMap as $directMapKey ) {
				$updateData[ $directMapKey ] = $meta->{$directMapKey};
				unset( $meta->{$directMapKey} );
			}

			$ts = Services::Request()->ts();
			$updateData[ 'backup_ready_at' ] = $meta->backupcode_validated ? $ts : 0;
			$updateData[ 'email_ready_at' ] = $meta->email_validated ? $ts : 0;
			$updateData[ 'ga_ready_at' ] = $meta->ga_validated ? $ts : 0;
			$updateData[ 'u2f_ready_at' ] = $meta->u2f_validated ? $ts : 0;
			$updateData[ 'yubi_ready_at' ] = $meta->yubi_validated ? $ts : 0;

			$dbh->getQueryUpdater()->updateRecord( $metaRecord, $updateData );

			$metaRecord = $metaLoader->loadMeta( $userID );
		}

		$meta->setUserMetaRecord( $metaRecord );
	}
}