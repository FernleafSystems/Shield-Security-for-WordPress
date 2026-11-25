<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\WordpressReleaseChannel;
use FernleafSystems\Wordpress\Services\{
	Services,
	Utilities\WpOrg
};

class RepairItem {

	use PluginControllerConsumer;
	use ScanItemConsumer;

	public function repairItem() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();
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
				}
			}
		}

		return $success;
	}

	/**
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		$canRepair = false;
		if ( $item->is_checksumfail || $item->is_missing ) {
			$pathFragment = \is_string( $item->path_fragment ) ? $item->path_fragment : '';
			if ( \trim( $pathFragment ) === '' ) {
				return false;
			}

			if ( $item->is_in_core ) {
				if ( !Services::CoreFileHashes()->isCoreFile( $pathFragment ) ) {
					throw new \Exception( __( 'Not a WordPress Core file', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: canRepair() failures are caught by RepairItem::repairItem(), LoadFileScanResultsTableData and the scan item action callers, never shown.
				}
				$canRepair = !( new WordpressReleaseChannel() )->isDevelopmentBuild();
			}
			elseif ( $item->is_in_plugin ) {
				$asset = Services::WpPlugins()->getPluginAsVo( $item->ptg_slug );

				if ( empty( $asset ) ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: canRepair() failures are caught by RepairItem::repairItem(), LoadFileScanResultsTableData and the scan item action callers, never shown.
					throw new \Exception( sprintf(
						/* translators: %s: plugin slug */
						__( "Couldn't load plugin for slug '%s'.", 'wp-simple-firewall' ),
						$item->ptg_slug
					) );
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				if ( !$asset->isWpOrg() ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: canRepair() failures are caught by RepairItem::repairItem(), LoadFileScanResultsTableData and the scan item action callers, never shown.
					throw new \Exception( sprintf(
						/* translators: %s: type of asset, such as Plugin */
						__( '%s not installed from WordPress.org.', 'wp-simple-firewall' ),
						__( 'Plugin', 'wp-simple-firewall' )
					) );
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				if ( !$asset->svn_uses_tags ) {
					throw new \Exception( __( "Plugin developer doesn't use SVN tags for official releases.", 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: canRepair() failures are caught by RepairItem::repairItem(), LoadFileScanResultsTableData and the scan item action callers, never shown.
				}

				try {
					$canRepair = ( new Lib\Hashes\Query() )->fileExistsInHash( $pathFragment );
				}
				catch ( \Exception $e ) {
				}
			}
			elseif ( $item->is_in_theme ) {
				$asset = Services::WpThemes()->getThemeAsVo( $item->ptg_slug );

				if ( empty( $asset ) ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: canRepair() failures are caught by RepairItem::repairItem(), LoadFileScanResultsTableData and the scan item action callers, never shown.
					throw new \Exception( sprintf(
						/* translators: %s: theme slug */
						__( "Couldn't load theme for slug '%s'.", 'wp-simple-firewall' ),
						$item->ptg_slug
					) );
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				if ( $asset->is_child ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: canRepair() failures are caught by RepairItem::repairItem(), LoadFileScanResultsTableData and the scan item action callers, never shown.
					throw new \Exception( sprintf(
						/* translators: %s: type of asset, such as Theme */
						__( "%s is a child of another theme.", 'wp-simple-firewall' ),
						__( 'Theme', 'wp-simple-firewall' )
					) );
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				if ( !$asset->isWpOrg() ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: canRepair() failures are caught by RepairItem::repairItem(), LoadFileScanResultsTableData and the scan item action callers, never shown.
					throw new \Exception( sprintf(
						/* translators: %s: type of asset, such as Theme */
						__( '%s not installed from WordPress.org.', 'wp-simple-firewall' ),
						__( 'Theme', 'wp-simple-firewall' )
					) );
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				if ( !( new WpOrg\Theme\Versions() )
					->setWorkingSlug( $asset->stylesheet )
					->exists( $asset->version, true ) ) {
					throw new \Exception( __( "Theme version doesn't appear to exist.", 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: canRepair() failures are caught by RepairItem::repairItem(), LoadFileScanResultsTableData and the scan item action callers, never shown.
				}

				try {
					$canRepair = ( new Lib\Hashes\Query() )->fileExistsInHash( $pathFragment );
				}
				catch ( \Exception $e ) {
				}
			}
		}

		return $canRepair;
	}

	private function repairCoreItem() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();
		try {
			$success = ( new WpOrg\Wp\Files() )->replaceFileFromVcs( $item->path_fragment );
		}
		catch ( \InvalidArgumentException $e ) {
			$success = false;
		}
		return $success;
	}

	private function repairItemInPlugin() :bool {
		/** @var ResultItem $item */
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
		/** @var ResultItem $item */
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
