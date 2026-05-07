const { expect } = require( './shield-test' );

const INLINE_TAB_HOST_SELECTOR = '[data-investigate-panel-tabs="1"]';
const INLINE_TAB_SELECTOR = '[data-investigate-panel-tab="1"]';
const SOURCE_TAB_SELECTOR = '.shield-options-rail [data-bs-toggle="tab"]';

function getInlineTabs( root ) {
	return root.locator( `${INLINE_TAB_HOST_SELECTOR} ${INLINE_TAB_SELECTOR}` );
}

async function expectInlineTabsContract( root, expectedCount = 4 ) {
	const tablist = root.locator( INLINE_TAB_HOST_SELECTOR );
	await expect( tablist ).toHaveCount( 1 );
	await expect( tablist ).toHaveAttribute( 'role', 'tablist' );
	await expect( tablist ).toHaveAttribute( 'aria-orientation', 'horizontal' );

	const sourceTabs = root.locator( SOURCE_TAB_SELECTOR );
	await expect( sourceTabs ).toHaveCount( expectedCount );

	const inlineTabs = getInlineTabs( root );
	await expect( inlineTabs ).toHaveCount( expectedCount );
	const tabContract = await inlineTabs.evaluateAll( ( tabs ) => tabs.map( ( tab ) => ( {
		id: tab.id,
		role: tab.getAttribute( 'role' ),
		controls: tab.getAttribute( 'aria-controls' ),
		selected: tab.getAttribute( 'aria-selected' ),
		sourceTabId: tab.dataset.sourceTabId || '',
		tabindex: tab.getAttribute( 'tabindex' ),
	} ) ) );

	expect( tabContract.every( ( tab ) => tab.id.length > 0 ) ).toBe( true );
	expect( tabContract.every( ( tab ) => tab.role === 'tab' ) ).toBe( true );
	expect( tabContract.every( ( tab ) => ( tab.controls || '' ).length > 0 ) ).toBe( true );
	expect( tabContract.every( ( tab ) => tab.sourceTabId.length > 0 ) ).toBe( true );
	expect( new Set( tabContract.map( ( tab ) => tab.id ) ).size ).toBe( tabContract.length );
	expect( new Set( tabContract.map( ( tab ) => tab.controls ) ).size ).toBe( tabContract.length );
	expect( tabContract.filter( ( tab ) => tab.selected === 'true' ) ).toHaveLength( 1 );
	expect( tabContract.filter( ( tab ) => tab.tabindex === '0' ) ).toHaveLength( 1 );
	expect(
		tabContract.every( ( tab ) => tab.selected === 'true' ? tab.tabindex === '0' : tab.tabindex === '-1' )
	).toBe( true );

	for ( const tab of tabContract ) {
		const pane = root.locator( `[id="${tab.controls}"][role="tabpanel"]` );
		await expect( pane ).toHaveCount( 1 );
		await expect( pane ).toHaveAttribute( 'aria-labelledby', tab.id );
		await expect(
			root.locator( `${SOURCE_TAB_SELECTOR}[id="${tab.sourceTabId}"][aria-controls="${tab.controls}"][data-bs-target="#${tab.controls}"]` )
		).toHaveCount( 1 );
	}
}

async function getInlineTabByIndex( root, index ) {
	const tab = getInlineTabs( root ).nth( index );
	await expect( tab ).toBeAttached();
	return tab;
}

async function getInlineTabByTableType( root, tableType ) {
	const table = root.locator( `table[data-investigation-table="1"][data-table-type="${tableType}"]` ).first();
	await expect( table ).toHaveCount( 1 );
	const panel = table.locator( 'xpath=ancestor::*[@role="tabpanel"][1]' );
	await expect( panel ).toHaveCount( 1 );
	const panelId = await panel.getAttribute( 'id' );
	expect( panelId || '' ).not.toHaveLength( 0 );

	const tab = root.locator( `${INLINE_TAB_SELECTOR}[aria-controls="${panelId}"]` ).first();
	await expect( tab ).toHaveCount( 1 );
	return tab;
}

async function expectActiveInlineTabState( root, inlineTab ) {
	const panelId = await inlineTab.getAttribute( 'aria-controls' );
	const inlineTabId = await inlineTab.getAttribute( 'id' );
	expect( panelId || '' ).not.toHaveLength( 0 );
	expect( inlineTabId || '' ).not.toHaveLength( 0 );

	await expect( inlineTab ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( inlineTab ).toHaveAttribute( 'tabindex', '0' );
	await expect( inlineTab ).toHaveClass( /(^|\s)active(\s|$)/ );
	await expect( inlineTab ).toHaveClass( /(^|\s)is-active(\s|$)/ );

	const inlineTabStates = await getInlineTabs( root ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => ( {
		id: tab.id,
		selected: tab.getAttribute( 'aria-selected' ),
		tabindex: tab.getAttribute( 'tabindex' ),
	} ) ) );
	expect( inlineTabStates.filter( ( tab ) => tab.selected === 'true' ) ).toHaveLength( 1 );
	expect( inlineTabStates.filter( ( tab ) => tab.tabindex === '0' ) ).toHaveLength( 1 );
	expect(
		inlineTabStates.every( ( tab ) => {
			if ( tab.id === inlineTabId ) {
				return tab.selected === 'true' && tab.tabindex === '0';
			}

			return tab.selected === 'false' && tab.tabindex === '-1';
		} )
	).toBe( true );

	const sourceTab = root.locator( `${SOURCE_TAB_SELECTOR}[aria-controls="${panelId}"]` ).first();
	await expect( sourceTab ).toHaveCount( 1 );
	await expect( sourceTab ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( sourceTab ).toHaveClass( /(^|\s)active(\s|$)/ );

	const panel = root.locator( `[id="${panelId}"][role="tabpanel"]` ).first();
	await expect( panel ).toHaveCount( 1 );
	await expect( panel ).toHaveAttribute( 'aria-labelledby', inlineTabId );
	await expect( panel ).toHaveClass( /(^|\s)active(\s|$)/ );
	await expect( panel ).toHaveClass( /(^|\s)show(\s|$)/ );
}

async function expectKeyboardTabActivation( page, root, fromTab, key, targetTab ) {
	await fromTab.focus();
	await expect( fromTab ).toBeFocused();
	await page.keyboard.press( key );

	await expect( targetTab ).toBeFocused();
	await expectActiveInlineTabState( root, targetTab );
}

module.exports = {
	expectActiveInlineTabState,
	expectInlineTabsContract,
	expectKeyboardTabActivation,
	getInlineTabByIndex,
	getInlineTabByTableType,
};
