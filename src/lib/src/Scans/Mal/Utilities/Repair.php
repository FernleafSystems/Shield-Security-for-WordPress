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

	/**
	 * @return bool
	 */
	public function repairItem() {
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

	/**
	 * @param ResultItem $item
	 * @return bool
	 */
	private function repairItemByDelete( $item ) {
		return Services\Services::WpFs()->deleteFile( $item->path_full );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() {
		return $this->canAutoRepairFromSource( $this->getScanItem() );
	}

	/**
	 * Can only repair a WP Core file, or a plugin that is WP.org, has no update available
	 * and the latest version uses SVN tags.
	 * @param ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	public function canAutoRepairFromSource( $oItem ) {
		$bCanRepair = Services\Services::CoreFileHashes()->isCoreFile( $oItem->path_fragment );
		if ( !$bCanRepair ) {

			$oPlugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $oItem->path_full );
			if ( $oPlugin instanceof Services\Core\VOs\Assets\WpPluginVo ) {
				if ( !$oPlugin->isWpOrg() ) {
					throw new \Exception( sprintf(
							__( "%s not installed from WordPress.org.", 'wp-simple-firewall' ),
							__( 'Plugin', 'wp-simple-firewall' )
						)
					);
				}
				if ( !$oPlugin->svn_uses_tags ) {
					throw new \Exception( __( "Plugin developer doesn't use SVN tags for official releases.", 'wp-simple-firewall' ) );
				}

				$bCanRepair = true;
			}
			else {
				$oTheme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $oItem->path_full );
				if ( $oTheme instanceof Services\Core\VOs\Assets\WpThemeVo ) {
					if ( $oTheme->is_child ) {
						throw new \Exception( sprintf(
								__( "%s is a child of another theme.", 'wp-simple-firewall' ),
								__( 'Theme', 'wp-simple-firewall' )
							)
						);
					}
					if ( !$oTheme->isWpOrg() ) {
						throw new \Exception( sprintf(
								__( "%s not installed from WordPress.org.", 'wp-simple-firewall' ),
								__( 'Theme', 'wp-simple-firewall' )
							)
						);
					}
					if ( !( new WpOrg\Theme\Versions() )
						->setWorkingSlug( $oTheme->stylesheet )
						->exists( $oTheme->version, true ) ) {
						throw new \Exception( __( "Theme version doesn't appear to exist.", 'wp-simple-firewall' ) );
					}

					$bCanRepair = true;
				}
			}
		}

		return $bCanRepair;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairCoreItem( $oItem ) {
		$oFiles = Services\Services::WpGeneral()->isClassicPress() ? new WpOrg\Cp\Files() : new WpOrg\Wp\Files();
		try {
			$bSuccess = $oFiles->replaceFileFromVcs( $oItem->path_fragment );
		}
		catch ( \InvalidArgumentException $e ) {
			$bSuccess = false;
		}
		return $bSuccess;
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
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairItemInTheme( $oItem ) {
		$success = false;

		$oFiles = new WpOrg\Theme\Files();
		try {
			if ( $oFiles->isValidFileFromTheme( $oItem->path_full ) ) {
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
}