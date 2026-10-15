/** Parse `#abc`, `#aabbcc` or `rgb(a, b, c)` into 0-255 channels. */
export function parseColor( value: string ): [ number, number, number ] {
	const v = value.trim().toLowerCase();
	const rgb = /^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/.exec( v );
	if ( rgb ) {
		return [ Number( rgb[ 1 ] ), Number( rgb[ 2 ] ), Number( rgb[ 3 ] ) ];
	}
	const short = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec( v );
	if ( short ) {
		return [ short[ 1 ], short[ 2 ], short[ 3 ] ].map( ( c ) => parseInt( c + c, 16 ) ) as [ number, number, number ];
	}
	const long = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/.exec( v );
	if ( long ) {
		return [ parseInt( long[ 1 ], 16 ), parseInt( long[ 2 ], 16 ), parseInt( long[ 3 ], 16 ) ];
	}
	throw new Error( `Cannot parse colour "${ value }"` );
}

function luminance( [ r, g, b ]: [ number, number, number ] ): number {
	const [ lr, lg, lb ] = [ r, g, b ].map( ( c ) => {
		const s = c / 255;
		return s <= 0.03928 ? s / 12.92 : ( ( s + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * lr + 0.7152 * lg + 0.0722 * lb;
}

/** WCAG 2.x contrast ratio. */
export function contrastRatio( a: string, b: string ): number {
	const [ hi, lo ] = [ luminance( parseColor( a ) ), luminance( parseColor( b ) ) ].sort( ( x, y ) => y - x );
	return ( hi + 0.05 ) / ( lo + 0.05 );
}
