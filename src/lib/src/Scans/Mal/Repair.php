<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class Repair extends Shield\Scans\Base\BaseRepair {

	use Shield\Modules\ModConsumer;

	/**
	 * @var bool
	 */
	private $bAllowDelete = false;

	/**
	 * @return bool
	 */
	public function isAllowDelete() {
		return (bool)$this->bAllowDelete;
	}

	/**
	 * @param bool $bAllowDelete
	 * @return $this
	 */
	public function setAllowDelete( $bAllowDelete ) {
		$this->bAllowDelete = $bAllowDelete;
		return $this;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	public function repairItem( $oItem ) {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$bSuccess = false;

		// 1) Report the file as being malware.
		( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
			->setMod( $this->getMod() )
			->report( $oItem->path_full, 'sha1', false );

		// 2). Repair

		try {
			$bCanAutoRepair = $this->canAutoRepairFromSource( $oItem );
		}
		catch ( \Exception $e ) {
			$bCanAutoRepair = false;
		}

		if ( $bCanAutoRepair ) {

			if ( Services\Services::CoreFileHashes()->isCoreFile( $oItem->path_fragment ) ) {
				$bSuccess = $this->repairCoreItem( $oItem );
			}
			else {
				$oPlugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $oItem->path_full );
				if ( $oPlugin instanceof Services\Core\VOs\WpPluginVo && $oPlugin->isWpOrg() ) {
					$bSuccess = $this->repairItemInPlugin( $oItem );
				}
				else if ( $oOpts->isMalAutoRepairSurgical() ) {
					$bSuccess = $this->repairSurgicalItem( $oItem );
				}
			}
		}
		else if ( $this->isAllowDelete() ) {
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
			$bCanRepair = ( $oPlugin instanceof Services\Core\VOs\WpPluginVo );
			if ( $bCanRepair ) {
				if ( !$oPlugin->isWpOrg() ) {
					throw new \Exception( sprintf(
							__( "%s not installed from WordPress.org.", 'wp-simple-firewall' ),
							__( 'Plugin', 'wp-simple-firewall' )
						)
					);
				};
				if ( !( new WpOrg\Plugin\Versions() )
					->setWorkingSlug( $oPlugin->slug )
					->exists( $oPlugin->Version, true ) ) {
					throw new \Exception( __( "Plugin developer doesn't use SVN tags for official releases.", 'wp-simple-firewall' ) );
				};
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
}