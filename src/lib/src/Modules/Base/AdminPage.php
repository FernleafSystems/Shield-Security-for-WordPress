<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PageAdminPlugin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 17.0
 */
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
		$con = $this->getCon();
		$mod = $this->getMod();

		if ( $this->getMod()->cfg->properties[ 'show_module_menu_item' ] ) {
			$this->screenID = add_submenu_page(
				$con->prefix(),
				$this->getPageTitle(),
				$this->getMenuTitle(),
				$this->getCap(),
				$this->getMod()->getModSlug(),
				[ $this, 'displayModuleAdminPage' ]
			);
		}

		foreach ( $this->getOptions()->getRawData_FullFeatureConfig()[ 'menu_items' ] ?? [] as $data ) {
			$title = __( $data[ 'title' ], 'wp-simple-firewall' );
			$title = ( $data[ 'highlight' ] ?? false ) ?
				sprintf( '<span class="shield_highlighted_menu">%s</span>', $title ) : $title;

			add_submenu_page(
				( $data[ 'slug' ] !== 'redirect-license' || !$mod->isPremium() ) ? $con->prefix() : null,
				$title,
				$title,
				$this->getCap(),
				$con->prefix( $data[ 'slug' ] ),
				[ $this, 'displayModuleAdminPage' ] /** Must have a valid callable callback **/
			);
		}
	}

	/**
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		echo $this->getCon()->action_router->render( PageAdminPlugin::SLUG, [] );
	}

	public function getScreenID() :string {
		return (string)$this->screenID;
	}

	public function getCap() :string {
		return $this->getCon()->getBasePermissions();
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

	public function getSlug() :string {
		return $this->getMod()->getModSlug();
	}
}