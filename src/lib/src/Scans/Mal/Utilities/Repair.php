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
		/** @var ResultItem $oItem */
		$oItem = $this->getScanItem();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$bSuccess = false;

		// 2). Repair
		try {
			$bCanAutoRepair = $this->canRepair();
		}
		catch ( \Exception $e ) {
			$bCanAutoRepair = false;
		}

		if ( $bCanAutoRepair || $this->isManualAction() ) {
			// 1) Report the file as being malware.
			( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
				->setMod( $this->getMod() )
				->reportResultItem( $oItem, false );
		}

		if ( $bCanAutoRepair ) {

			if ( Services\Services::CoreFileHashes()->isCoreFile( $oItem->path_fragment ) ) {
				$bSuccess = $this->repairCoreItem( $oItem );
			}
			else {
				$oPlugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $oItem->path_full );
				if ( $oPlugin instanceof Services\Core\VOs\WpPluginVo && $oPlugin->isWpOrg() ) {

					if ( $this->isManualAction() || $oOpts->isRepairFilePlugin() ) {
						$bSuccess = $this->repairItemInPlugin( $oItem );
					}
				}
				else {
					$oTheme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $oItem->path_full );
					if ( $oTheme instanceof Services\Core\VOs\WpThemeVo && $oTheme->isWpOrg() ) {

						if ( $this->isManualAction() || $oOpts->isRepairFileTheme() ) {
							$bSuccess = $this->repairItemInTheme( $oItem );
						}
					}
					elseif ( $oOpts->isMalAutoRepairSurgical() ) {
						$bSuccess = $this->repairSurgicalItem( $oItem );
					}
				}
			}
		}
		elseif ( $this->isAllowDelete() ) {
			$bSuccess = $this->repairItemByDelete( $oItem );
		}

		return $bSuccess;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairItemByDelete( $oItem ) {
		return Services\Services::WpFs()->deleteFile( $oItem->path_full );
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
			if ( $oPlugin instanceof Services\Core\VOs\WpPluginVo ) {
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
				if ( $oTheme instanceof Services\Core\VOs\WpThemeVo ) {
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
		catch ( \InvalidArgumentException $oE ) {
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
			catch ( \Exception $oE ) {
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
		$oFiles = new WpOrg\Plugin\Files();
		try {
			if ( $oFiles->isValidFileFromPlugin( $oItem->path_full ) ) {
				$bSuccess = $oFiles->replaceFileFromVcs( $oItem->path_full );
			}
			else {
				$bSuccess = Services\Services::WpFs()->deleteFile( $oItem->path_full );
			}
		}
		catch ( \InvalidArgumentException $oE ) {
			$bSuccess = false;
		}
		return (bool)$bSuccess;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairItemInTheme( $oItem ) {
		$oFiles = new WpOrg\Theme\Files();
		try {
			if ( $oFiles->isValidFileFromTheme( $oItem->path_full ) ) {
				$bSuccess = $oFiles->replaceFileFromVcs( $oItem->path_full );
			}
			else {
				$bSuccess = Services\Services::WpFs()->deleteFile( $oItem->path_full );
			}
		}
		catch ( \InvalidArgumentException $oE ) {
			$bSuccess = false;
		}
		return (bool)$bSuccess;
	}
}