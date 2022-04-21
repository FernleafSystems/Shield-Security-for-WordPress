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
		}, $this->getMod()->cfg->properties[ 'menu_priority' ] );
	}

	protected function addSubMenuItem() {
		if ( $this->getMod()->cfg->properties[ 'show_module_menu_item' ] ) {
			$this->screenID = add_submenu_page(
				$this->getCon()->prefix(),
				$this->getPageTitle(),
				$this->getMenuTitle(),
				$this->getCap(),
				$this->getMod()->getModSlug(),
				[ $this, 'displayModuleAdminPage' ]
			);
		}

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
			Services::DataManipulation()->mergeArraysRecursive(
				$this->getMod()->getUIHandler()->getBaseDisplayData(),
				$data
			)
		);
	}

	/**
	 * @deprecated 15.0
	 */
	protected function getMenuPriority() :int {
		return $this->getMod()->cfg->properties[ 'menu_priority' ] ?? 100;
	}

	public function getCap() :string {
		return $this->getCon()->getBasePermissions();
	}

	/**
	 * @deprecated 15.0
	 */
	public function isShowMenu() :bool {
		return $this->getMod()->cfg->properties[ 'show_module_menu_item' ] ?? false;
	}

	public function isCurrentPage() :bool {
		$req = Services::Request();
		return !Services::WpGeneral()->isAjax() && $req->isGet()
			   && $this->getCon()->isModulePage() && $req->query( 'page' ) == $this->getSlug();
	}

	public function getMenuTitle( bool $markup = true ) :string {
		$mod = $this->getMod();

		$title = __( $mod->cfg->properties[ 'menu_title' ], 'wp-simple-firewall' );

		if ( $markup && $mod->cfg->properties[ 'highlight_menu_item' ] ) {
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

	public function getSlug() :string {
		return $this->getMod()->getModSlug();
	}
}