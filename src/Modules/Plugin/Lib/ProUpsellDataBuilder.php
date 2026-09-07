<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ProUpsellDataBuilder {

	use PluginControllerConsumer;

	/**
	 * @return array{
	 *   template_id:string,
	 *   title_id:string,
	 *   logo_url:string,
	 *   left_lines:list<array{text:string,emphasis:string}>,
	 *   heading:string,
	 *   labels:array{close:string,protection:string,free:string,pro:string,included:string,not_included:string,view_pro_plans:string,compare_features:string},
	 *   rows:list<array{label:string,free:bool,pro:bool}>,
	 *   hrefs:array{go_pro:string,compare_features:string}
	 * }
	 */
	public function build() :array {
		return [
			'template_id' => 'shield-pro-upsell-template',
			'title_id'    => 'shield-pro-upsell-title',
			'logo_url'    => self::con()->urls->forImage( 'plugin_logo_prem_dark.svg' ),
			'left_lines'  => [
				[
					'text'     => __( 'Free protection is powerful.', 'wp-simple-firewall' ),
					'emphasis' => '',
				],
				[
					'text'     => __( 'Pro protection is ', 'wp-simple-firewall' ),
					'emphasis' => __( 'complete.', 'wp-simple-firewall' ),
				],
			],
			'heading'     => __( 'Level-Up Your WordPress Protection', 'wp-simple-firewall' ),
			'labels'      => [
				'close'            => __( 'Close', 'wp-simple-firewall' ),
				'protection'       => __( 'Protection', 'wp-simple-firewall' ),
				'free'             => __( 'Free', 'wp-simple-firewall' ),
				'pro'              => __( 'Pro', 'wp-simple-firewall' ),
				'included'         => __( 'Included', 'wp-simple-firewall' ),
				'not_included'     => __( 'Not included', 'wp-simple-firewall' ),
				'view_pro_plans'   => __( 'View Pro plans', 'wp-simple-firewall' ),
				'compare_features' => __( 'Compare every feature', 'wp-simple-firewall' ),
			],
			'rows'        => [
				[ 'label' => __( 'Core hardening', 'wp-simple-firewall' ), 'free' => true, 'pro' => true ],
				[ 'label' => __( 'Auto bad bot blocking', 'wp-simple-firewall' ), 'free' => true, 'pro' => true ],
				[ 'label' => __( 'WP core file scanning', 'wp-simple-firewall' ), 'free' => true, 'pro' => true ],
				[ 'label' => __( 'Malware scanning with MAL{ai}', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
				[ 'label' => __( 'Vulnerability detection', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
				[ 'label' => __( 'Plugins and themes file scanning', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
				[ 'label' => __( 'Critical File Locker (wp-config.php)', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
				[ 'label' => __( 'ShieldBACKUP Disaster Recovery', 'wp-simple-firewall' ), 'free' => false, 'pro' => true ],
			],
			'hrefs'       => [
				'go_pro'           => BaseRender::GO_PRO_URL,
				'compare_features' => BaseRender::COMPARE_FEATURES_URL,
			],
		];
	}
}
