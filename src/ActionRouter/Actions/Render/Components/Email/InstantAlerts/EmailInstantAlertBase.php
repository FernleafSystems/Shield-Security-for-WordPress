<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Text\SafeDisplayText;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type InstantAlertLine array{label:string,value:string,style:string}
 * @phpstan-type InstantAlertItem array{href:string,lines:list<InstantAlertLine>}
 * @phpstan-type InstantAlertGroup array{title:string,items:list<InstantAlertItem>}
 */
abstract class EmailInstantAlertBase extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\EmailBase {

	public const TEMPLATE = '/email/instant_alerts/instant_alert_standard.twig';
	public const LINE_STYLE_TEXT = 'text';
	public const LINE_STYLE_CODE = 'code';

	protected function getBodyData() :array {
		return [
			'hrefs'   => [
				'url_site'  => Services::WpGeneral()->getHomeUrl(),
				'url_users' => Services::WpGeneral()->getAdminUrl( 'users.php' ),
			],
			'strings' => [
				'url_site' => __( 'Site Address (URL)', 'wp-simple-firewall' ),
				'intro'    => [],
				'outro'    => [],
			],
			'vars'    => [
				'alert_groups' => $this->buildAlertGroups(),
			],
		];
	}

	protected function buildAlertGroups() :array {
		return [];
	}

	/**
	 * @param list<InstantAlertItem> $items
	 * @return InstantAlertGroup
	 */
	protected function alertGroup( string $title, array $items ) :array {
		return [
			'title' => $this->displayText( $title ),
			'items' => \array_values( $items ),
		];
	}

	/**
	 * @param list<InstantAlertLine> $lines
	 * @return InstantAlertItem
	 */
	protected function alertItem( array $lines, string $href = '' ) :array {
		return [
			'href'  => $this->safeHref( $href ),
			'lines' => \array_values( $lines ),
		];
	}

	/**
	 * @param mixed $value
	 * @return InstantAlertLine
	 */
	protected function alertLine( string $label, $value, string $style = self::LINE_STYLE_TEXT ) :array {
		return [
			'label' => $this->displayText( $label ),
			'value' => $this->displayText( $value ),
			'style' => \in_array( $style, [ self::LINE_STYLE_TEXT, self::LINE_STYLE_CODE ], true )
				? $style
				: self::LINE_STYLE_TEXT,
		];
	}

	/**
	 * @param mixed $value
	 * @return InstantAlertLine
	 */
	protected function alertTextLine( $value ) :array {
		return $this->alertLine( '', $value );
	}

	/**
	 * @param mixed $value
	 */
	protected function displayText( $value ) :string {
		return SafeDisplayText::inline( $value );
	}

	private function safeHref( string $href ) :string {
		$href = \trim( $href );
		if ( $href === '' || \preg_match( '#^\s*javascript:#i', $href ) ) {
			return '';
		}

		$sanitised = \function_exists( 'esc_url_raw' ) ? esc_url_raw( $href ) : $href;
		return $this->displayText( $sanitised );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'alert_data',
		];
	}
}
