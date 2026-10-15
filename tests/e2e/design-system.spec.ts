import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { contrastRatio } from './color';
import { contrastRows, cssTokenNames, css, documentedClasses, tokenRows } from './design-doc';

const DASHBOARD = '/wp-admin/admin.php?page=silver-assist';
const EMPTY = '/wp-admin/admin.php?page=silver-assist&sa_e2e=empty';
const PLUGIN_PAGE = '/wp-admin/admin.php?page=e2e-security';

/** Radii allowed by the design system, in px. Pill is any value of 9999 or more. */
const RADII = [ 0, 6, 8, 12 ];

async function rootToken( page: Page, name: string ): Promise<string> {
	return page.evaluate(
		( n ) => getComputedStyle( document.documentElement ).getPropertyValue( n ).trim().toLowerCase(),
		name
	);
}

test.describe( 'documentation matches the stylesheet', () => {
	test( 'the doc parser finds the tables it checks', () => {
		expect( tokenRows().length ).toBeGreaterThan( 40 );
		expect( contrastRows().length ).toBeGreaterThan( 5 );
		expect( documentedClasses().length ).toBeGreaterThan( 8 );
	} );

	test( 'every documented token has the documented value', async ( { page } ) => {
		await page.goto( DASHBOARD );
		const failures: string[] = [];

		for ( const { token, value } of tokenRows() ) {
			const actual = await rootToken( page, token );
			const expected = value.startsWith( '--silver-hub-' ) ? await rootToken( page, value ) : value.toLowerCase();
			if ( '' === actual || actual !== expected ) {
				failures.push( `${ token }: doc says "${ value }" (${ expected }), CSS gives "${ actual }"` );
			}
		}

		expect( failures ).toEqual( [] );
	} );

	test( 'every non-legacy token in the CSS is documented', () => {
		const documented = new Set( tokenRows().map( ( r ) => r.token ) );
		// Pre-1.3 names are documented as a group in "For consumer plugins".
		const legacy = /^--silver-hub-(color|spacing|font-size|font-weight|border-color|bg|success|radius-(md|lg)|shadow-card|fluid)/;
		const missing = cssTokenNames().filter( ( n ) => ! documented.has( n ) && ! legacy.test( n ) );
		expect( missing ).toEqual( [] );
	} );

	test( 'every documented component class exists in the CSS', () => {
		const missing = documentedClasses().filter( ( c ) => ! css.includes( c ) );
		expect( missing ).toEqual( [] );
	} );

	test( 'the contrast table is correct and meets WCAG AA', async ( { page } ) => {
		await page.goto( DASHBOARD );
		const problems: string[] = [];

		for ( const row of contrastRows() ) {
			const fg = await rootToken( page, row.foreground );
			const bg = row.background.startsWith( '--' ) ? await rootToken( page, row.background ) : row.background;
			const actual = contrastRatio( fg, bg );
			const minimum = row.nonText ? 3 : 4.5;

			if ( Math.abs( actual - row.ratio ) > 0.01 ) {
				problems.push( `${ row.foreground } on ${ row.background }: doc says ${ row.ratio }, actual ${ actual.toFixed( 2 ) }` );
			}
			if ( actual < minimum ) {
				problems.push( `${ row.foreground } on ${ row.background }: ${ actual.toFixed( 2 ) } is below ${ minimum }` );
			}
		}

		expect( problems ).toEqual( [] );
	} );
} );

test.describe( 'dashboard', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( DASHBOARD );
	} );

	test( 'cards are labelled articles with exactly one filled action', async ( { page } ) => {
		const cards = page.locator( 'article.silverassist-plugin-card' );
		await expect( cards ).toHaveCount( 3 );

		for ( const card of await cards.all() ) {
			const labelledBy = await card.getAttribute( 'aria-labelledby' );
			expect( labelledBy ).toBeTruthy();
			await expect( card.locator( `#${ labelledBy }` ) ).toBeVisible();
			await expect( card.locator( '.button-primary' ) ).toHaveCount( 1 );
		}
	} );

	test( 'the card is bordered, flat at rest and does not move on hover', async ( { page } ) => {
		const card = page.locator( '.silverassist-plugin-card' ).first();
		const read = () =>
			card.evaluate( ( el ) => {
				const s = getComputedStyle( el );
				return {
					border: `${ s.borderTopWidth } ${ s.borderTopStyle } ${ s.borderTopColor }`,
					radius: s.borderTopLeftRadius,
					shadow: s.boxShadow,
					transform: s.transform,
					top: el.getBoundingClientRect().top,
				};
			} );

		const rest = await read();
		expect( rest.border ).toBe( '1px solid rgb(229, 229, 229)' );
		expect( rest.radius ).toBe( '12px' );
		expect( rest.shadow ).toBe( 'none' );

		await card.hover();
		await page.waitForTimeout( 250 );
		const hover = await read();
		expect( hover.transform ).toBe( 'none' );
		expect( hover.top ).toBe( rest.top );
		expect( hover.shadow ).toBe( 'rgba(0, 0, 0, 0.05) 0px 1px 2px 0px' );
	} );

	test( 'the primary button is brand navy and the others are outlined', async ( { page } ) => {
		const card = page.locator( '.silverassist-plugin-card' ).first();
		const style = ( loc: ReturnType<Page['locator']> ) =>
			loc.evaluate( ( el ) => {
				const s = getComputedStyle( el );
				return { bg: s.backgroundColor, color: s.color, border: s.borderTopColor, radius: s.borderTopLeftRadius, height: el.getBoundingClientRect().height };
			} );

		const primary = await style( card.locator( '.button-primary' ) );
		expect( primary ).toMatchObject( { bg: 'rgb(0, 48, 115)', color: 'rgb(255, 255, 255)', radius: '8px', height: 32 } );

		await card.locator( '.button-primary' ).hover();
		await page.waitForTimeout( 250 );
		expect( ( await style( card.locator( '.button-primary' ) ) ).bg ).toBe( 'rgb(0, 27, 64)' );

		const secondary = await style( card.locator( '.button:not(.button-primary)' ) );
		expect( secondary ).toMatchObject( { bg: 'rgb(255, 255, 255)', border: 'rgb(229, 229, 229)', radius: '8px' } );
	} );

	test( 'the version is a neutral pill, not a status colour', async ( { page } ) => {
		const pill = page.locator( '.silverassist-version-badge' ).first();
		const s = await pill.evaluate( ( el ) => {
			const c = getComputedStyle( el );
			return { bg: c.backgroundColor, radius: parseFloat( c.borderTopLeftRadius ), font: c.fontFamily };
		} );
		expect( s.bg ).toBe( 'rgb(245, 245, 245)' );
		expect( s.radius ).toBeGreaterThanOrEqual( 9999 );
		expect( s.font ).toMatch( /mono|menlo|consolas/i );
	} );

	test( 'tabs are pills, with one active tab marked for assistive tech', async ( { page } ) => {
		const nav = page.locator( 'nav.silverassist-hub-tabs' );
		await expect( nav ).toHaveAttribute( 'aria-label', /.+/ );
		await expect( nav.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );

		const active = nav.locator( '.nav-tab-active' );
		await expect( active ).toHaveAttribute( 'aria-current', 'page' );
		await expect( active ).toHaveCSS( 'background-color', 'rgb(219, 234, 254)' );
		await expect( active ).toHaveCSS( 'color', 'rgb(0, 48, 115)' );

		const idle = nav.locator( '.nav-tab:not(.nav-tab-active)' ).first();
		await expect( idle ).toHaveCSS( 'background-color', 'rgba(0, 0, 0, 0)' );
		const radius = await idle.evaluate( ( el ) => parseFloat( getComputedStyle( el ).borderTopLeftRadius ) );
		expect( radius ).toBeGreaterThanOrEqual( 9999 );
	} );

	test( 'the intro shows the plugin count', async ( { page } ) => {
		await expect( page.locator( '.silverassist-dashboard-count' ) ).toHaveText( '3 plugins' );
	} );

	test( 'cards in a row have equal height and actions pinned to the bottom', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		const boxes = await page.locator( '.silverassist-plugin-card' ).evaluateAll( ( els ) =>
			els.map( ( el ) => {
				const r = el.getBoundingClientRect();
				const a = el.querySelector( '.plugin-actions' )!.getBoundingClientRect();
				return { top: Math.round( r.top ), height: Math.round( r.height ), actionsGap: Math.round( r.bottom - a.bottom ) };
			} )
		);

		expect( new Set( boxes.map( ( b ) => b.top ) ).size ).toBe( 1 );
		expect( new Set( boxes.map( ( b ) => b.height ) ).size ).toBe( 1 );
		expect( new Set( boxes.map( ( b ) => b.actionsGap ) ).size ).toBe( 1 );
	} );

	test( 'only the documented radii are used', async ( { page } ) => {
		const offenders = await page.evaluate( ( allowed ) => {
			const found: string[] = [];
			const nodes = document.querySelectorAll(
				'.wrap [class*="silverassist"], .wrap [class*="card-"], .wrap .plugin-actions, .wrap .plugin-actions *, .wrap .version-badge'
			);
			for ( const el of nodes ) {
				const s = getComputedStyle( el );
				for ( const corner of [ s.borderTopLeftRadius, s.borderTopRightRadius, s.borderBottomRightRadius, s.borderBottomLeftRadius ] ) {
					const px = parseFloat( corner );
					if ( ! allowed.includes( px ) && px < 9999 ) {
						found.push( `${ el.className || el.tagName }: ${ corner }` );
					}
				}
			}
			return found;
		}, RADII );

		expect( offenders ).toEqual( [] );
	} );

	test( 'no hub element uses a heavy shadow or a webfont', async ( { page } ) => {
		const result = await page.evaluate( () => {
			const heavy: string[] = [];
			for ( const el of document.querySelectorAll( '.wrap [class*="silverassist"], .wrap .plugin-actions .button' ) ) {
				const shadow = getComputedStyle( el ).boxShadow;
				if ( 'none' !== shadow && ! /0px 1px 2px/.test( shadow ) ) {
					heavy.push( `${ el.className }: ${ shadow }` );
				}
			}
			return { heavy, fontFaces: [ ...document.fonts ].filter( ( f ) => /satoshi|inter|geist/i.test( f.family ) ).length };
		} );

		expect( result.heavy ).toEqual( [] );
		expect( result.fontFaces ).toBe( 0 );
	} );

	test( 'keyboard focus shows the brand teal ring on tabs and buttons', async ( { page } ) => {
		const seen = new Set<string>();

		for ( let i = 0; i < 200 && seen.size < 2; i++ ) {
			await page.keyboard.press( 'Tab' );
			const focus = await page.evaluate( () => {
				const el = document.activeElement as HTMLElement | null;
				if ( ! el || ! el.closest( '.silverassist-hub-tabs, .plugin-actions' ) ) {
					return null;
				}
				const s = getComputedStyle( el );
				return {
					kind: el.closest( '.silverassist-hub-tabs' ) ? 'tab' : 'button',
					outline: `${ s.outlineWidth } ${ s.outlineStyle } ${ s.outlineColor }`,
					offset: s.outlineOffset,
				};
			} );

			if ( focus ) {
				expect( focus.outline, `${ focus.kind } focus ring` ).toBe( '2px solid rgb(45, 138, 179)' );
				expect( focus.offset ).toBe( '2px' );
				seen.add( focus.kind );
			}
		}

		expect( [ ...seen ].sort() ).toEqual( [ 'button', 'tab' ] );
	} );

	test( 'transitions are removed when the user prefers reduced motion', async ( { page } ) => {
		const card = page.locator( '.silverassist-plugin-card' ).first();
		await expect( card ).toHaveCSS( 'transition-duration', '0.15s, 0.15s' );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await expect( card ).toHaveCSS( 'transition-duration', '0s' );
		await expect( page.locator( '.silverassist-hub-tabs .nav-tab' ).first() ).toHaveCSS( 'transition-duration', '0s' );
	} );
} );

test.describe( 'small screens', () => {
	test.use( { viewport: { width: 360, height: 740 } } );

	test( 'the dashboard has no horizontal page scroll and stacks its actions', async ( { page } ) => {
		await page.goto( DASHBOARD );

		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		expect( overflow ).toBeLessThanOrEqual( 0 );

		const card = page.locator( '.silverassist-plugin-card' ).first();
		const cardWidth = ( await card.boundingBox() )!.width;
		const button = card.locator( '.button-primary' );
		const box = ( await button.boundingBox() )!;
		expect( box.height ).toBe( 40 );
		expect( box.width ).toBeGreaterThan( cardWidth * 0.8 );
	} );

	test( 'tabs stay on one line and scroll inside their own container', async ( { page } ) => {
		await page.goto( DASHBOARD );
		const nav = page.locator( 'nav.silverassist-hub-tabs' );
		await expect( nav ).toHaveCSS( 'overflow-x', 'auto' );
		await expect( nav ).toHaveCSS( 'flex-wrap', 'nowrap' );
	} );
} );

test.describe( 'empty state', () => {
	test( 'explains what to do next', async ( { page } ) => {
		await page.goto( EMPTY );
		const empty = page.locator( '.silverassist-empty-state' );
		await expect( empty ).toBeVisible();
		await expect( empty ).toContainText( 'No Silver Assist plugins have been registered yet.' );
		await expect( empty.locator( '.silverassist-empty-state-hint' ) ).toBeVisible();
		await expect( empty ).toHaveCSS( 'border-top-style', 'dashed' );
		await expect( empty ).toHaveCSS( 'border-top-left-radius', '12px' );
		await expect( page.locator( '.silverassist-dashboard-grid' ) ).toHaveCount( 0 );
		await expect( page.locator( '.silverassist-dashboard-count' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'plugin page', () => {
	test( 'wraps consumer content with the hub header and tabs', async ( { page } ) => {
		await page.goto( PLUGIN_PAGE );

		const title = page.locator( '.wrap > h1' );
		await expect( title ).toContainText( 'Security' );
		await expect( title.locator( '.version-badge' ) ).toHaveText( 'v1.4.2' );
		await expect( title.locator( '.version-badge' ) ).toHaveCSS( 'background-color', 'rgb(245, 245, 245)' );

		const active = page.locator( 'nav.silverassist-hub-tabs [aria-current="page"]' );
		await expect( active ).toHaveCount( 1 );
		await expect( active ).toHaveText( 'Security' );

		await expect( page.locator( '.form-table' ) ).toBeVisible();
	} );
} );

test.describe( 'accessibility (axe, WCAG 2 A and AA)', () => {
	for ( const [ name, url ] of [ [ 'dashboard', DASHBOARD ], [ 'empty state', EMPTY ], [ 'plugin page', PLUGIN_PAGE ] ] ) {
		test( `${ name } has no violations inside the hub`, async ( { page } ) => {
			await page.goto( url );
			const results = await new AxeBuilder( { page } )
				.include( '.wrap' )
				.withTags( [ 'wcag2a', 'wcag2aa' ] )
				.analyze();

			expect(
				results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` )
			).toEqual( [] );
		} );
	}
} );

test( 'screenshots for review', async ( { page }, testInfo ) => {
	for ( const width of [ 1440, 782, 360 ] ) {
		await page.setViewportSize( { width, height: 900 } );
		await page.goto( DASHBOARD );
		await testInfo.attach( `dashboard-${ width }`, { body: await page.screenshot( { fullPage: true } ), contentType: 'image/png' } );
	}
} );
