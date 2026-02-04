<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers\NinjaForms;

final class NinjaForms_ShieldSpamAction extends \NF_Abstracts_Action {

	/**
	 * @var string
	 */
	protected $_name = 'shieldantibot';

	private NinjaForms $shieldNinjaFormsHandler;

	/**
	 * @var array
	 */
	protected $_tags = [ 'spam', 'filtering', 'shield' ];

	public function __construct() {
		parent::__construct();
		$this->_nicename = esc_html( sprintf( __( '%s Anti-Spam', 'wp-simple-firewall' ), self::con()->labels->Name ) );
	}

	public function setHandler( NinjaForms $handler ) :self {
		$this->shieldNinjaFormsHandler = $handler;
		return $this;
	}

	public function process( $action_settings, $form_id, $data ) {
		if ( $this->shieldNinjaFormsHandler->isBotBlockRequired() ) {
			$data[ 'errors' ][ 'form' ][ 'spam' ] = esc_html( __( 'There was an error trying to send your message. Please try again later', 'wp-simple-firewall' ) );
		}
		return $data;
	}
}