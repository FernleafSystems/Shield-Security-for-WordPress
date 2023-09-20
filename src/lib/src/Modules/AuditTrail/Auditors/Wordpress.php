<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\ZoneReportWordpress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\DiffVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\SnapWordpress;
use FernleafSystems\Wordpress\Services\Services;

class Wordpress extends Base {

	public function canSnapRealtime() :bool {
		return true;
	}

	protected function initAuditHooks() :void {
		add_action( '_core_updated_successfully', [ $this, 'auditCoreUpdated' ] );
		foreach ( \array_keys( $this->getMapOptionKeysToEvent() ) as $optionKey ) {
			add_action( 'update_option_'.$optionKey, [ $this, 'auditWpOptions' ], 10, 3 );
		}
	}

	private function getMapOptionKeysToEvent() :array {
		return [
			'permalink_structure' => 'permalinks_structure',
			'admin_email'         => 'wp_option_admin_email',
			'blogname'            => 'wp_option_blogname',
			'blogdescription'     => 'wp_option_blogdescription',
			'default_role'        => 'wp_option_default_role',
			'users_can_register'  => 'wp_option_users_can_register',
			'home'                => 'wp_option_home',
			'siteurl'             => 'wp_option_siteurl',
		];
	}

	public function auditWpOptions( $old, $new, $option ) {
		// So that the logs for checkboxes is more humane. Must also align with what SnapWP does.
		if ( $option === 'users_can_register' ) {
			$old = $old == 0 ? 'off' : 'on';
			$new = $new == 0 ? 'off' : 'on';
		}

		$this->fireAuditEvent( $this->getMapOptionKeysToEvent()[ $option ], [
			'from' => $old,
			'to'   => $new,
		] );
	}

	/**
	 * @param string $newVersion
	 */
	public function auditCoreUpdated( $newVersion ) {
		if ( Services::WpGeneral()->getVersion() === $newVersion ) {
			$this->fireAuditEvent( 'core_reinstalled', [
				'version' => $newVersion,
			] );
		}
		elseif ( !empty( $newVersion ) ) {
			$this->fireAuditEvent( 'core_updated', [
				'from' => Services::WpGeneral()->getVersion(),
				'to'   => $newVersion,
			] );
		}
	}

	/**
	 * @snapshotDiff
	 */
	public function snapshotDiffForOptions( DiffVO $diff ) {
		if ( isset( $diff->changed[ 'options' ] ) ) {
			$old = $diff->changed[ 'options' ][ 'old' ];
			$new = $diff->changed[ 'options' ][ 'new' ];

			foreach ( \array_keys( $old ) as $eventSlug ) {
				$oldValue = $old[ $eventSlug ];
				$newValue = $new[ $eventSlug ];
				if ( $oldValue !== $newValue ) {
					switch ( $eventSlug ) {
						case 'permalinks_structure':
						case 'wp_option_admin_email':
						case 'wp_option_blogname':
						case 'wp_option_blogdescription':
						case 'wp_option_default_role':
						case 'wp_option_users_can_register':
						case 'wp_option_home':
						case 'wp_option_siteurl':
							$this->fireAuditEvent( $eventSlug, [
								'from' => $oldValue,
								'to'   => $newValue,
							] );
							break;
						default:
							break;
					}
				}
			}
		}
		if ( isset( $diff->changed[ 'core' ] ) ) {
			$old = $diff->changed[ 'core' ][ 'old' ];
			$new = $diff->changed[ 'core' ][ 'new' ];

			foreach ( \array_keys( $old ) as $slug ) {
				$oldValue = $old[ $slug ];
				$newValue = $new[ $slug ];
				if ( $oldValue !== $newValue ) {
					switch ( $slug ) {
						case 'version':
							$this->fireAuditEvent( 'core_updated', [
								'from' => $oldValue,
								'to'   => $newValue,
							] );
							break;
						default:
							break;
					}
				}
			}
		}
	}

	public function getSnapper() :SnapWordpress {
		return new SnapWordpress();
	}

	public function getReporter() :ZoneReportWordpress {
		return new ZoneReportWordpress();
	}
}