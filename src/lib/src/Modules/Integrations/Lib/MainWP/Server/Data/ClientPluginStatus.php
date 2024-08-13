<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\Consumers\MWPSiteConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ClientPluginStatus {

	use PluginControllerConsumer;
	use MWPSiteConsumer;

	public const ACTIVE = 'acti';
	public const NEED_SYNC = 'nsync';
	public const NOT_PRO = 'npro';
	public const MWP_NOT_ON = 'mwpnoton';
	public const INACTIVE = 'inact';
	public const NOT_INSTALLED = 'ninst';
	public const VERSION_NEWER_THAN_SERVER = 'vnts';
	public const VERSION_OLDER_THAN_SERVER = 'vots';

	public function status() :string {
		return \key( $this->detect() );
	}

	/**
	 * TODO: Consider things like global disabled / forceoff
	 */
	public function detect() :array {
		$sync = LoadShieldSyncData::Load( $this->getMwpSite() );
		$m = $sync->meta;

		if ( $this->isActive() ) {

			if ( empty( $sync->getRawData() ) ) {
				$status = self::NEED_SYNC;
			}
			elseif ( empty( $m->is_pro ) ) {
				$status = self::NOT_PRO;
			}
			elseif ( empty( $m->is_mainwp_on ) ) {
				$status = self::MWP_NOT_ON;
			}
			else {
				$versionStatus = \version_compare( self::con()->cfg->version(), $m->version );
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

	public function getInstalledPlugin() :?array {
		$thePlugin = null;

		$baseName = \basename( self::con()->base_file );
		foreach ( $this->getMwpSite()->plugins as $plugin ) {
			if ( \basename( $plugin[ 'slug' ] ) === $baseName ) {
				$thePlugin = $plugin;
				break;
			}
		}

		return $thePlugin;
	}

	public function isActive() :bool {
		return $this->isInstalled() && !empty( $this->getInstalledPlugin()[ 'active' ] );
	}

	public function isInstalled() :bool {
		return !empty( $this->getInstalledPlugin() );
	}

	public function getStatusText() :array {
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