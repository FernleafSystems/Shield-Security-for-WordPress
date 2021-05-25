<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AdminPage extends ExecOnceModConsumer {

	protected $screenID;

	protected function canRun() :bool {
		return !Services::WpGeneral()->isAjax() && ( is_admin() || is_network_admin() );
	}

	protected function run() {
		$con = $this->getCon();
		add_action( $con->prefix( 'admin_submenu' ), function () {
			$this->addSubMenuItem();
		}, $this->getMenuPriority() );
	}

	protected function addSubMenuItem() {
		$this->screenID = add_submenu_page(
			$this->isShowMenu() ? $this->getCon()->prefix() : null,
			$this->getPageTitle(),
			$this->getMenuTitle(),
			$this->getCap(),
			$this->getMod()->getModSlug(),
			[ $this, 'displayModuleAdminPage' ]
		);

		foreach ( $this->getAdditionalMenuItems() as $additionalMenuItem ) {
			list( $itemText, $itemID, $itemCallback, $showItem ) = $additionalMenuItem;
			add_submenu_page(
				$showItem ? $this->getCon()->prefix() : null,
				$itemText,
				$itemText,
				$this->getCap(),
				$itemID,
				$itemCallback
			);
		}
	}

	/**
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		echo $this->renderModulePage();
	}

	public function getScreenID() :string {
		return (string)$this->screenID;
	}

	/**
	 * Override this to customize anything with the display of the page
	 * @param array $data
	 * @return string
	 */
	protected function renderModulePage( array $data = [] ) :string {
		return $this->getMod()->renderTemplate(
			'index.php',
			Services::DataManipulation()->mergeArraysRecursive( $this->getMod()->getUIHandler()
																	 ->getBaseDisplayData(), $data )
		);
	}

	protected function getMenuPriority() :int {
		$pri = $this->getOptions()->getFeatureProperty( 'menu_priority' );
		return is_null( $pri ) ? 100 : (int)$pri;
	}

	public function getCap() :string {
		return $this->getCon()->getBasePermissions();
	}

	public function isShowMenu() :bool {
		return (bool)$this->getOptions()->getFeatureProperty( 'show_module_menu_item' );
	}

	public function getMenuTitle( bool $markup = true ) :string {
		$mod = $this->getMod();
		$title = $this->getOptions()->getFeatureProperty( 'menu_title' );
		$title = empty( $title ) ? $mod->getMainFeatureName() : __( $title, 'wp-simple-firewall' );
		if ( $markup && $this->getOptions()->getFeatureProperty( 'highlight_menu_item' ) ) {
			$title = sprintf( '<span class="shield_highlighted_menu">%s</span>', $title );
		}
		return $title;
	}

	public function getPageTitle() :string {
		return sprintf( '%s - %s', $this->getMenuTitle( false ), $this->getCon()->getHumanName() );
	}

	public function getAdditionalMenuItems() :array {
		$items = [];

		$con = $this->getCon();
		$mod = $this->getMod();
		foreach ( $this->getOptions()->getAdditionalMenuItems() as $menuItem ) {

			// special case: don't show go pro if you're pro.
			if ( $menuItem[ 'slug' ] !== 'pro-redirect' || !$mod->isPremium() ) {

				$title = __( $menuItem[ 'title' ], 'wp-simple-firewall' );
				$menuPageTitle = $con->getHumanName().' - '.$title;
				$isHighlighted = $menuItem[ 'highlight' ] ?? false;
				$items[ $menuPageTitle ] = [
					$isHighlighted ? sprintf( '<span class="shield_highlighted_menu">%s</span>', $title ) : $title,
					$mod->prefix( $menuItem[ 'slug' ] ),
					[ $this, $menuItem[ 'callback' ] ?? '' ],
					true
				];
			}
		}
		return $items;
	}
}