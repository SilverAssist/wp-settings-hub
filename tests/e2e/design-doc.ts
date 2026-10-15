import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = join( __dirname, '..', '..' );

export interface TokenRow {
	token: string;
	/** Literal value, or another token name when the cell holds a `--silver-hub-*` reference. */
	value: string;
}

export interface ContrastRow {
	foreground: string;
	background: string;
	ratio: number;
	/** Non-text pairs (focus ring) only need 3:1. */
	nonText: boolean;
}

const doc = readFileSync( join( ROOT, 'docs', 'design-system.md' ), 'utf8' );
export const css = readFileSync( join( ROOT, 'assets', 'css', 'settings-hub.css' ), 'utf8' );

/** Split the document into `[heading, lines]` blocks on `##` and `###` headings. */
function sections(): Array<{ heading: string; lines: string[] }> {
	const blocks: Array<{ heading: string; lines: string[] }> = [];
	for ( const line of doc.split( '\n' ) ) {
		const heading = /^#{2,3}\s+(.*)$/.exec( line );
		if ( heading ) {
			blocks.push( { heading: heading[ 1 ], lines: [] } );
		} else if ( blocks.length ) {
			blocks[ blocks.length - 1 ].lines.push( line );
		}
	}
	return blocks;
}

/** Every `| \`--silver-hub-x\` | \`value\` | ... |` row outside the Contrast section. */
export function tokenRows(): TokenRow[] {
	const rows: TokenRow[] = [];
	for ( const { heading, lines } of sections() ) {
		if ( 'Contrast' === heading ) {
			continue;
		}
		for ( const line of lines ) {
			const match = /^\|\s*`(--silver-hub-[\w-]+)`\s*\|\s*`([^`]+)`\s*\|/.exec( line );
			if ( match ) {
				rows.push( { token: match[ 1 ], value: match[ 2 ] } );
			}
		}
	}
	return rows;
}

/** Rows of the Contrast table. */
export function contrastRows(): ContrastRow[] {
	const block = sections().find( ( s ) => 'Contrast' === s.heading );
	const rows: ContrastRow[] = [];
	for ( const line of block?.lines ?? [] ) {
		const cells = line.split( '|' ).map( ( c ) => c.trim() ).filter( Boolean );
		const ratio = /^(\d+(?:\.\d+)?):1$/.exec( cells[ 2 ] ?? '' );
		const foreground = /`(--silver-hub-[\w-]+)`/.exec( cells[ 0 ] ?? '' );
		const background = /`([^`]+)`/.exec( cells[ 1 ] ?? '' );
		if ( ratio && foreground && background ) {
			rows.push( {
				foreground: foreground[ 1 ],
				background: background[ 1 ],
				ratio: parseFloat( ratio[ 1 ] ),
				nonText: /non-text/.test( cells[ 0 ] ),
			} );
		}
	}
	return rows;
}

/** Class selectors named in backticks in the Components table, such as `.silverassist-plugin-card`. */
export function documentedClasses(): string[] {
	const block = sections().find( ( s ) => 'Components' === s.heading );
	const classes = new Set<string>();
	for ( const line of block?.lines ?? [] ) {
		const cell = line.split( '|' ).map( ( c ) => c.trim() )[ 2 ] ?? '';
		for ( const match of cell.matchAll( /`(\.[\w-]+)`/g ) ) {
			classes.add( match[ 1 ] );
		}
	}
	return [ ...classes ];
}

/** Every declared custom property name in the stylesheet. */
export function cssTokenNames(): string[] {
	return [ ...new Set( [ ...css.matchAll( /(--silver-hub-[\w-]+)\s*:/g ) ].map( ( m ) => m[ 1 ] ) ) ];
}
