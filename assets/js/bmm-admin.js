jQuery( function ( $ ) {
	'use strict';

	// ── Sponsorship repeater ──────────────────────────────────────────────────

	let rowIndex = $( '#bmm-sponsorships-body tr' ).length;

	$( '#bmm-add-sponsorship' ).on( 'click', function () {
		const row = `<tr class="bmm-sponsorship-row">
			<td><input type="checkbox" name="bmm_sponsorship_enabled[]" value="${ rowIndex }" checked /></td>
			<td><input type="text" name="bmm_sponsorship_id[]" value="" class="regular-text" /></td>
			<td><input type="text" name="bmm_sponsorship_label[]" value="" class="regular-text" /></td>
			<td><input type="number" name="bmm_sponsorship_amount[]" value="0" min="0" class="small-text" /></td>
			<td><button type="button" class="button bmm-remove-sponsorship">Remove</button></td>
		</tr>`;
		$( '#bmm-sponsorships-body' ).append( row );
		rowIndex++;
	} );

	$( '#bmm-sponsorships-body' ).on( 'click', '.bmm-remove-sponsorship', function () {
		$( this ).closest( 'tr' ).remove();
	} );

	// Reindex enabled checkboxes on form submit so PHP array aligns with rows
	$( 'form#post' ).on( 'submit', function () {
		$( '#bmm-sponsorships-body tr' ).each( function ( i ) {
			$( this ).find( 'input[name="bmm_sponsorship_enabled[]"]' ).val( i );
		} );
	} );
} );
