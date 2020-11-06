<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\Consumers\MWPSiteConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class ClientPluginStatus {

	use ModConsumer;
	use MWPSiteConsumer;

	const ACTIVE = 'acti';
	const NEED_SYNC = 'nsync';
	const NOT_PRO = 'npro';
	const MWP_NOT_ON = 'mwpnoton';
	const INACTIVE = 'inact';
	const NOT_INSTALLED = 'ninst';
	const VERSION_NEWER_THAN_SERVER = 'vnts';
	const VERSION_OLDER_THAN_SERVER = 'vots';

	public function status() :string {
		$status = $this->detect();
		return key( $status );
	}

	/**
	 * TODO: Consider things like global disabled / forceoff
	 * @return array
	 */
	public function detect() :array {
		$sync = LoadShieldSyncData::Load( $this->getMwpSite() );
		$m = $sync->meta;

		if ( $this->isActive() ) {

			if ( empty( $sync->getRawDataAsArray() ) ) {
				$status = self::NEED_SYNC;
			}
			elseif ( empty( $m->is_pro ) ) {
				$status = self::NOT_PRO;
			}
			elseif ( empty( $m->is_mainwp_on ) ) {
				$status = self::MWP_NOT_ON;
			}
			else {
				$versionStatus = version_compare( $this->getCon()->getVersion(), $m->version );
				if ( $versionStatus === -1 ) {
					$status = self::VERSION_NEWER_THAN_SERVER;
				}
				elseif ( $versionStatus === 1 ) {
					$status = self::VERSION_OLDER_THAN_SERVER;
				}
				else {
					$status = self::ACTIVE;
				}
			}
		}
		elseif ( $this->isInstalled() ) {
			$status = self::INACTIVE;
		}
		else {
			$status = self::NOT_INSTALLED;
		}
		return [ $status => $this->getStatusText()[ $status ] ];
	}

	/**
	 * @return array|null
	 */
	public function getInstalledPlugin() {
		$thePlugin = null;

		$baseName = basename( $this->getCon()->getPluginBaseFile() );
		foreach ( $this->getMwpSite()->plugins as $plugin ) {
			if ( basename( $plugin[ 'slug' ] ) === $baseName ) {
				$thePlugin = $plugin;
				break;
			}
		}

		return $thePlugin;
	}

	public function isActive() :bool {
		return !empty( $this->getInstalledPlugin()[ 'active' ] );
	}

	public function isInstalled() :bool {
		return !empty( $this->getInstalledPlugin() );
	}

	public function getStatusText() {
		return [
			self::ACTIVE                    => __( 'Active' ),
			self::NOT_PRO                   => __( 'Not Pro' ),
			self::MWP_NOT_ON                => __( 'MainWP Option Not Enabled' ),
			self::NEED_SYNC                 => __( 'Sync Required' ),
			self::INACTIVE                  => __( 'Installed' ),
			self::NOT_INSTALLED             => __( 'Not Installed' ),
			self::VERSION_OLDER_THAN_SERVER => __( 'Update Required' ),
			self::VERSION_NEWER_THAN_SERVER => __( 'Ahead Of Server' ),
		];
	}
}