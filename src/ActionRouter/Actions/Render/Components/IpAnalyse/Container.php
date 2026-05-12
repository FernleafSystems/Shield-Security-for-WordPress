<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;

class Container extends Base {

	public const SLUG = 'ipanalyse_container';
	public const TEMPLATE = '/wpadmin/components/ip_analyse/container.twig';

	protected function getRenderData() :array {
		$ip = $this->getAnalyseIP();
		$tabs = $this->buildTabs();
		$actionRouter = self::con()->action_router;

		return [
			'flags'   => [
				'render_inline_tabs' => (bool)( $this->action_data[ 'render_inline_tabs' ] ?? false ),
			],
			'content' => [
				'general'  => $actionRouter->render( General::class, [
					'ip' => $ip,
				] ),
				'sessions' => $actionRouter->render( Sessions::class, [
					'ip' => $ip,
				] ),
				'activity' => $actionRouter->render( Activity::class, [
					'ip' => $ip,
				] ),
				'traffic'  => $actionRouter->render( Traffic::class, [
					'ip' => $ip,
				] ),
			],
			'tabs'    => $tabs,
		];
	}

	/**
	 * @return list<array{
	 *   target:string,
	 *   id:string,
	 *   controls:string,
	 *   label:string,
	 *   is_focus:bool,
	 *   content_key:string,
	 *   panel_body_class:string
	 * }>
	 */
	private function buildTabs() :array {
		$instanceId = 'ipanalyse-'.\uniqid();
		return [
			$this->buildTab(
				$instanceId,
				'general',
				__( 'Overview', 'wp-simple-firewall' ),
				true,
				'general',
				'p-0'
			),
			$this->buildTab(
				$instanceId,
				'sessions',
				CommonDisplayStrings::get( 'user_sessions_label' ),
				false,
				'sessions'
			),
			$this->buildTab(
				$instanceId,
				'audit',
				__( 'Activity Log', 'wp-simple-firewall' ),
				false,
				'activity'
			),
			$this->buildTab(
				$instanceId,
				'traffic',
				__( 'Recent Traffic', 'wp-simple-firewall' ),
				false,
				'traffic'
			),
		];
	}

	/**
	 * @return array{
	 *   target:string,
	 *   id:string,
	 *   controls:string,
	 *   label:string,
	 *   is_focus:bool,
	 *   content_key:string,
	 *   panel_body_class:string
	 * }
	 */
	private function buildTab(
		string $instanceId,
		string $key,
		string $label,
		bool $isFocus,
		string $contentKey,
		string $panelBodyClass = ''
	) :array {
		$panelId = $instanceId.'-'.$key;

		return [
			'target'           => '#'.$panelId,
			'id'               => $instanceId.'-nav-'.$key,
			'controls'         => $panelId,
			'label'            => $label,
			'is_focus'         => $isFocus,
			'content_key'      => $contentKey,
			'panel_body_class' => $panelBodyClass,
		];
	}
}
