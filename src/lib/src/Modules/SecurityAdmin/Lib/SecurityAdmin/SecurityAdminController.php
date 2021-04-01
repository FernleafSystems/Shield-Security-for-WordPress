<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class SecurityAdminController {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {

		add_filter( $this->getCon()->prefix( 'is_plugin_admin' ), [ $this, 'adjustUserAdminPermissions' ] );

		add_action( 'init', function () {
			if ( !$this->getCon()->isPluginAdmin() ) {
				( new Restrictions\WpOptions() )
					->setMod( $this->getMod() )
					->execute();
				( new Restrictions\Plugins() )
					->setMod( $this->getMod() )
					->execute();
				( new Restrictions\Themes() )
					->setMod( $this->getMod() )
					->execute();
				( new Restrictions\Posts() )
					->setMod( $this->getMod() )
					->execute();
				( new Restrictions\Users() )
					->setMod( $this->getMod() )
					->execute();

				if ( !$this->getCon()->isThisPluginModuleRequest() ) {
					add_action( 'admin_footer', [ $this, 'printAdminAccessAjaxForm' ] );
				}
			}
		} );
	}

	public function adjustUserAdminPermissions( $isPluginAdmin = true ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $isPluginAdmin &&
			   ( $mod->isRegisteredSecAdminUser() || $mod->isSecAdminSessionValid() || $mod->testSecAccessKeyRequest() );
	}

	public function printAdminAccessAjaxForm() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aRenderData = [
			'flags'       => [
				'restrict_options' => $opts->getAdminAccessArea_Options()
			],
			'strings'     => [
				'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
				'unlock_link'        => sprintf(
					'<a href="%1$s" title="%2$s" class="thickbox">%3$s</a>',
					'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
					__( 'Security Admin Login', 'wp-simple-firewall' ),
					__( 'Unlock', 'wp-simple-firewall' )
				),
			],
			'js_snippets' => [
				'options_to_restrict' => "'".implode( "','", $opts->getOptionsToRestrict() )."'",
			],
			'ajax'        => [
				'sec_admin_login_box' => $mod->getAjaxActionData( 'sec_admin_login_box', true )
			]
		];
		add_thickbox();
		echo $mod->renderTemplate( 'snippets/admin_access_login_box.php', $aRenderData );
	}
}