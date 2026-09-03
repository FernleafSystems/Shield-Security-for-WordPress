async function expectCardFocusRingWithinGrid( card, gridSelector, expect ) {
	await expect.poll(
		async () => await card.evaluate( ( element ) => {
			const style = window.getComputedStyle( element );
			const accentProbe = document.createElement( 'span' );
			accentProbe.style.color = style.getPropertyValue( '--operator-card-accent-color' );
			document.body.appendChild( accentProbe );
			const accentColor = window.getComputedStyle( accentProbe ).color;
			accentProbe.remove();
			return style.borderColor === accentColor;
		} ),
		{ timeout: 2_000 }
	).toBe( true );

	const focusRing = await card.evaluate( ( element, selector ) => {
		const bounds = element.getBoundingClientRect();
		const style = window.getComputedStyle( element );
		const outlineWidth = Number.parseFloat( style.outlineWidth ) || 0;
		const outlineOffset = Number.parseFloat( style.outlineOffset ) || 0;
		const gridBounds = element.closest( selector )?.getBoundingClientRect();

		return {
			borderColor: style.borderColor,
			left: bounds.left - outlineWidth - outlineOffset,
			outlineColor: style.outlineColor,
			right: bounds.right + outlineWidth + outlineOffset,
			gridLeft: gridBounds?.left ?? null,
			gridRight: gridBounds?.right ?? null,
		};
	}, gridSelector );

	expect( focusRing.gridLeft ).not.toBeNull();
	expect( focusRing.gridRight ).not.toBeNull();
	expect( focusRing.left ).toBeGreaterThanOrEqual( focusRing.gridLeft - 0.5 );
	expect( focusRing.right ).toBeLessThanOrEqual( focusRing.gridRight + 0.5 );
	expect( focusRing.outlineColor ).toBe( focusRing.borderColor );
}

module.exports = {
	expectCardFocusRingWithinGrid,
};
