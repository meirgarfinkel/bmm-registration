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

	// ── Submissions list: confirm the "Delete" bulk action ────────────────────
	// The delete action moves the selected submissions to Trash; ask first so an
	// accidental Apply cannot wipe a selection silently.
	$( '#doaction, #doaction2' ).on( 'click', function ( e ) {
		const which  = this.id === 'doaction2' ? '2' : '';
		const action = $( 'select[name="action' + which + '"]' ).val();
		if ( action !== 'delete' ) {
			return;
		}
		const checked = $( 'input[name="submission_ids[]"]:checked' ).length;
		if ( checked === 0 ) {
			return; // nothing selected — let WP handle the no-op
		}
		const msg = checked === 1
			? 'Move 1 submission to Trash?'
			: 'Move ' + checked + ' submissions to Trash?';
		if ( ! window.confirm( msg ) ) {
			e.preventDefault();
		}
	} );
} );
