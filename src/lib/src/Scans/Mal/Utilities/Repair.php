<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\ResultItem;
use FernleafSystems\Wordpress\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class Repair extends Shield\Scans\Base\Utilities\BaseRepair {

	use Shield\Modules\ModConsumer;

	public function repairItem() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();
		/** @var Shield\Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		$success = false;

		try {
			$canRepair = $this->canRepair();
		}
		catch ( \Exception $e ) {
			$canRepair = false;
		}

		if ( $canRepair ) {

			if ( Services\Services::CoreFileHashes()->isCoreFile( $item->path_fragment ) ) {
				$success = $this->repairCoreItem( $item );
			}
			else {
				$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $item->path_full );
				if ( $plugin instanceof Services\Core\VOs\Assets\WpPluginVo && $plugin->isWpOrg() ) {

					$success = $this->repairItemInPlugin( $item );
				}
				else {
					$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $item->path_full );
					if ( $theme instanceof Services\Core\VOs\Assets\WpThemeVo && $theme->isWpOrg() ) {

						$success = $this->repairItemInTheme( $item );
					}
					elseif ( $opts->isMalAutoRepairSurgical() ) {
						$success = $this->repairSurgicalItem( $item );
					}
				}
			}
		}
		elseif ( $this->isAllowDelete() ) {
			$success = $this->repairItemByDelete( $item );
		}

		if ( $success ) {
			// 1) Report the file as being malware.
			( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
				->setMod( $this->getMod() )
				->reportResultItem( $item, false );
		}

		return $success;
	}

	private function repairItemByDelete( ResultItem $item ) :bool {
		return (bool)Services\Services::WpFs()->deleteFile( $item->path_full );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		return $this->canAutoRepairFromSource( $this->getScanItem() );
	}

	/**
	 * Can only repair a WP Core file, or a plugin that is WP.org, has no update available
	 * and the latest version uses SVN tags.
	 * @param ResultItem $item
	 * @return bool
	 * @throws \Exception
	 */
	public function canAutoRepairFromSource( ResultItem $item ) :bool {

		$canRepair = Services\Services::CoreFileHashes()->isCoreFile( $item->path_fragment );
		if ( !$canRepair ) {

			$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $item->path_full );
			if ( !empty( $plugin ) ) {
				if ( !$plugin->isWpOrg() ) {
					throw new \Exception( sprintf(
							__( "%s not installed from WordPress.org.", 'wp-simple-firewall' ),
							__( 'Plugin', 'wp-simple-firewall' )
						)
					);
				}
				if ( !$plugin->svn_uses_tags ) {
					throw new \Exception( __( "Plugin developer doesn't use SVN tags for official releases.", 'wp-simple-firewall' ) );
				}

				$canRepair = true;
			}
			else {
				$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $item->path_full );
				if ( !empty( $theme ) ) {
					if ( $theme->is_child ) {
						throw new \Exception( sprintf(
								__( "%s is a child of another theme.", 'wp-simple-firewall' ),
								__( 'Theme', 'wp-simple-firewall' )
							)
						);
					}
					if ( !$theme->isWpOrg() ) {
						throw new \Exception( sprintf(
								__( "%s not installed from WordPress.org.", 'wp-simple-firewall' ),
								__( 'Theme', 'wp-simple-firewall' )
							)
						);
					}
					if ( !( new WpOrg\Theme\Versions() )
						->setWorkingSlug( $theme->stylesheet )
						->exists( $theme->version, true ) ) {
						throw new \Exception( __( "Theme version doesn't appear to exist.", 'wp-simple-firewall' ) );
					}

					$canRepair = true;
				}
			}
		}

		return $canRepair;
	}

	/**
	 * @param ResultItem $item
	 * @return bool
	 */
	private function repairCoreItem( $item ) :bool {
		$files = Services\Services::WpGeneral()->isClassicPress() ? new WpOrg\Cp\Files() : new WpOrg\Wp\Files();
		try {
			$success = $files->replaceFileFromVcs( $item->path_fragment );
		}
		catch ( \InvalidArgumentException $e ) {
			$success = false;
		}
		return $success;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairSurgicalItem( $oItem ) {
		$bSuccess = false;
		foreach ( $oItem->file_lines as $nLine ) {
			try {
				( new Services\Utilities\File\RemoveLineFromFile() )->run( $oItem->path_full, $nLine );
				$bSuccess = true;
			}
			catch ( \Exception $e ) {
				$bSuccess = false;
				break;
			}
		}
		return $bSuccess;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairItemInPlugin( $oItem ) {
		$success = false;

		$oFiles = new WpOrg\Plugin\Files();
		try {
			if ( $oFiles->isValidFileFromPlugin( $oItem->path_full ) ) {
				$success = $oFiles->replaceFileFromVcs( $oItem->path_full );
			}
			elseif ( $this->isAllowDelete() ) {
				$success = (bool)Services\Services::WpFs()->deleteFile( $oItem->path_full );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}

		return $success;
	}

	/**
	 * @param ResultItem $item
	 * @return bool
	 */
	private function repairItemInTheme( $item ) :bool {
		$success = false;

		$oFiles = new WpOrg\Theme\Files();
		try {
			if ( $oFiles->isValidFileFromTheme( $item->path_full ) ) {
				$success = $oFiles->replaceFileFromVcs( $item->path_full );
			}
			elseif ( $this->isAllowDelete() ) {
				$success = (bool)Services\Services::WpFs()->deleteFile( $item->path_full );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}

		return $success;
	}
}