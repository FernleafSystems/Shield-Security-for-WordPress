<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;
use FernleafSystems\Wordpress\Services\{
	Services,
	Utilities\File\RemoveLineFromFile,
	Utilities\WpOrg
};

class RepairItem extends Shield\Scans\Base\Utilities\RepairItemBase {

	use Shield\Modules\ModConsumer;
	use Shield\Scans\Common\ScanItemConsumer;

	public function repairItem() :bool {
		/** @var Afs\ResultItem $item */
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

			if ( Services::CoreFileHashes()->isCoreFile( $item->path_fragment ) ) {
				$success = $this->repairCoreItem();
			}
			else {
				$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $item->path_full );
				if ( !empty( $plugin ) ) {
					$success = $this->repairItemInPlugin();
				}
				else {
					$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $item->path_full );
					if ( !empty( $theme ) && $theme->isWpOrg() ) {
						$success = $this->repairItemInTheme();
					}
					elseif ( $opts->isMalAutoRepairSurgical() ) {
						$success = $this->repairSurgicalItem();
					}
				}
			}
		}

		if ( $success && $item->is_mal ) {
			( new MalFalsePositiveReporter() )
				->setMod( $this->getMod() )
				->reportResultItem( $item, false );
		}

		return $success;
	}

	/**
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		/** @var Afs\ResultItem $item */
		$item = $this->getScanItem();

		$canRepair = false;
		if ( $item->is_checksumfail || $item->is_missing ) {

			if ( $item->is_in_core ) {
				if ( !Services::CoreFileHashes()->isCoreFile( $item->path_fragment ) ) {
					throw new \Exception( __( 'Not a WordPress Core file', 'wp-simple-firewall' ) );
				}
				$canRepair = true;
			}
			elseif ( $item->is_in_plugin ) {
				$asset = Services::WpPlugins()->getPluginAsVo( $item->ptg_slug );

				if ( empty( $asset ) ) {
					throw new \Exception( sprintf(
						__( "Couldn't load plugin for slug '%s'.", 'wp-simple-firewall' ),
						$item->ptg_slug
					) );
				}
				if ( !$asset->isWpOrg() ) {
					throw new \Exception( sprintf(
						__( '%s not installed from WordPress.org.', 'wp-simple-firewall' ),
						__( 'Plugin', 'wp-simple-firewall' )
					) );
				}
				if ( !$asset->svn_uses_tags ) {
					throw new \Exception( __( "Plugin developer doesn't use SVN tags for official releases.", 'wp-simple-firewall' ) );
				}

				try {
					$canRepair = ( new Shield\Modules\HackGuard\Lib\Hashes\Query() )
						->setMod( $this->getMod() )
						->fileExistsInHash( $item->path_fragment );
				}
				catch ( \Exception $e ) {
					error_log( var_export( $e->getMessage(), true ) );
					$canRepair = false;
				}
			}
			elseif ( $item->is_in_theme ) {
				$asset = Services::WpThemes()->getThemeAsVo( $item->ptg_slug );

				if ( empty( $asset ) ) {
					throw new \Exception( sprintf(
						__( "Couldn't load theme for slug '%s'.", 'wp-simple-firewall' ),
						$item->ptg_slug
					) );
				}
				if ( $asset->is_child ) {
					throw new \Exception( sprintf(
						__( "%s is a child of another theme.", 'wp-simple-firewall' ),
						__( 'Theme', 'wp-simple-firewall' )
					) );
				}
				if ( !$asset->isWpOrg() ) {
					throw new \Exception( sprintf(
						__( '%s not installed from WordPress.org.', 'wp-simple-firewall' ),
						__( 'Theme', 'wp-simple-firewall' )
					) );
				}
				if ( !( new WpOrg\Theme\Versions() )
					->setWorkingSlug( $asset->stylesheet )
					->exists( $asset->version, true ) ) {
					throw new \Exception( __( "Theme version doesn't appear to exist.", 'wp-simple-firewall' ) );
				}

				try {
					$canRepair = ( new Shield\Modules\HackGuard\Lib\Hashes\Query() )
						->setMod( $this->getMod() )
						->fileExistsInHash( $item->path_fragment );
				}
				catch ( \Exception $e ) {
					error_log( var_export( $e->getMessage(), true ) );
					$canRepair = false;
				}
			}
		}

		return $canRepair;
	}

	private function repairCoreItem() :bool {
		/** @var Afs\ResultItem $item */
		$item = $this->getScanItem();

		$files = Services::WpGeneral()->isClassicPress() ? new WpOrg\Cp\Files() : new WpOrg\Wp\Files();
		try {
			$success = $files->replaceFileFromVcs( $item->path_fragment );
		}
		catch ( \InvalidArgumentException $e ) {
			$success = false;
		}
		return $success;
	}

	private function repairSurgicalItem() :bool {
		/** @var Afs\ResultItem $item */
		$item = $this->getScanItem();

		$success = false;
		foreach ( array_keys( $item->fp_lines ) as $lineNumber ) {
			try {
				( new RemoveLineFromFile() )->run( $item->path_full, $lineNumber );
				$success = true;
			}
			catch ( \Exception $e ) {
				$success = false;
				break;
			}
		}
		return $success;
	}

	private function repairItemInPlugin() :bool {
		/** @var Afs\ResultItem $item */
		$item = $this->getScanItem();

		$success = false;

		$files = new WpOrg\Plugin\Files();
		try {
			if ( $files->isValidFileFromPlugin( $item->path_full ) ) {
				$success = $files->replaceFileFromVcs( $item->path_full );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}

		return $success;
	}

	private function repairItemInTheme() :bool {
		/** @var Afs\ResultItem $item */
		$item = $this->getScanItem();

		$success = false;

		$files = new WpOrg\Theme\Files();
		try {
			if ( $files->isValidFileFromTheme( $item->path_full ) ) {
				$success = $files->replaceFileFromVcs( $item->path_full );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}

		return $success;
	}
}