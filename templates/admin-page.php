<div class="wrap" id="kanban_gravityforms">
	<h1>
		Kanban + Gravity Forms
	</h1>

	<?php if ( !class_exists( 'GFAPI' ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php echo sprintf(
					__('Please install and activate %s.', 'kanban'),
					'<a href="http://gravityforms.com" target="_blank">Gravity Forms</a>'
				)
				?>
			</p>
		</div>
	<?php endif ?>

	<?php if ( isset($_GET['message']) ) : ?>
	<div class="updated">
		<p><?php echo $_GET['message'] ?></p>
	</div>
	<?php endif // notice ?>



	<?php if ( class_exists( 'GFAPI' ) ) : ?>
	<p>
		<?php echo __('Choose a Gravity Form form to edit', 'kanban') ?>:
		<select id="select-gform" autocomplete="off">
			<option value="">-- <?php echo __('Choose a form', 'kanban') ?> --</option>
			<?php foreach ( $forms as $form ) : ?>
				<option value="<?php echo $form[ 'id' ] ?>">
					<?php echo $form[ 'title' ] ?>
				</option>
			<?php endforeach; // $forms ?>
		</select>
	</p>



	<hr>
	<?php endif ?>



		<?php $i = 0;
		foreach ( $forms as $form ) : ?>
			<form action="" method="post" class="gform" id="gform-<?php echo $form[ 'id' ] ?>" style="display: none;">

				<?php wp_nonce_field( self::$slug, self::$slug . '-nonce' ) ?>

				<h2>
					<small><?php echo __('Form', 'kanban') ?>:</small>
					<br>
					<b style="font-size: 1.618em">
						<?php echo $form[ 'title' ] ?>
					</b>
				</h2>

				<p>
					1.
					<?php echo __('Choose a board to sync with', 'kanban') ?>:
					<?php if ( count($boards) > 1 ) : ?>
					<select name="forms[<?php echo $form[ 'id' ] ?>][board]" class="board_id" autocomplete="off">
						<option value="">-- <?php echo __('Choose a board', 'kanban') ?> --</option>
						<?php foreach ( $boards as $board ) : ?>
							<option
								value="<?php echo $board->id ?>" <?php echo isset( $saved[ $form[ 'id' ] ][ 'board' ] ) && $saved[ $form[ 'id' ] ][ 'board' ] == $board->id ? 'selected' : '' ?>>
								<?php echo $board->title ?>
							</option>
						<?php endforeach; // $forms ?>
					</select>
					<?php else : $board = reset($boards) ?>
						<input name="forms[<?php echo $form[ 'id' ] ?>][board]" type="hidden" value="<?php echo $board->id ?>" class="board_id">
						<input type="text" value="<?php echo esc_attr($board->title) ?>" readonly>
					<?php endif ?>
				</p>

				<p>
					2.
					<?php echo __('Map form fields to task data', 'kanban') ?>:
				</p>

				<table style="background: white; min-width: 50%;">
					<tbody>
					<?php foreach ( $form[ 'fields' ] as $field ) : ?>
						<tr class="<?php echo $i % 2 == 0 ? '' : 'alternate' ?>">
							<td>
								<?php echo $field->label ?>
							</td>
							<td>
								&rarr;
							</td>
							<td>
								<select
									name="forms[<?php echo $form[ 'id' ] ?>][<?php echo $field->id ?>][table_column]"
									data-defaultValue="forms[<?php echo $form[ 'id' ] ?>][<?php echo $field->id ?>][defaultValue]"
									data-value="<?php echo isset( $saved[ $form[ 'id' ] ][ $field->id ][ 'defaultValue' ] ) ? $saved[ $form[ 'id' ] ][ $field->id ][ 'defaultValue' ] : '' ?>"
									autocomplete="off" style="width: 100%;" class="table_column">
									<option value="">
										-- <?php echo __('Map to', 'kanban') ?> --
									</option>
									<?php foreach ( $table_columns as $id => $label ) : ?>
										<option
											value="<?php echo $id ?>" <?php echo isset( $saved[ $form[ 'id' ] ][ $field->id ] ) && $saved[ $form[ 'id' ] ][ $field->id ][ 'table_column' ] == $id ? 'selected' : '' ?>>
											<?php echo $label ?>
										</option>
									<?php endforeach; // $table_columns ?>
								</select>

								<?php // endforeach; // $table_columns ?>
							</td>
							<td>

								<?php if ( $field[ 'type' ] == 'hidden' ) : ?>
									<select
										name="forms[<?php echo $form[ 'id' ] ?>][<?php echo $field->id ?>][defaultValue]"
										autocomplete="off" style="width: 100%;" class="">
									</select>
								<?php endif ?>
							</td>
						</tr>
						<?php $i++; endforeach; // $field ?>
					</tbody>
				</table>

				<?php if ( class_exists( 'GFAPI' ) ) : ?>
					<?php submit_button( __('Save your settings', 'kanban') ); ?>
				<?php endif ?>

			</form><!-- form -->
		<?php endforeach; // $forms ?>



	<div style="display: none">
		<?php foreach ( $boards as $board ) : ?>
			<select id="project_id<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->projects as $project ) : ?>
					<option
						value="<?php echo $project->id ?>">
						<?php echo $project->title ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="status_id<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->statuses as $status ) : ?>
					<option
						value="<?php echo $status->id ?>">
						<?php echo $status->title ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="estimate_id<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->estimates as $estimate ) : ?>
					<option
						value="<?php echo $estimate->id ?>">
						<?php echo $estimate->title ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="user_id_assigned<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->users as $user ) : ?>
					<option
						value="<?php echo $user->ID ?>">
						<?php echo $user->long_name_email ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="user_id_author<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->users as $user ) : ?>
					<option
						value="<?php echo $user->ID ?>">
						<?php echo $user->long_name_email ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php echo apply_filters('kanban_gravity_forms_extra_selects', '', $board->id) ?>

		<?php endforeach; // $forms ?>
	</div>


</div><!-- wrap -->


<script>
	jQuery( function ( $ ) {
		$( '#select-gform' ).on(
			'change',
			function () {
				var $select = $( this );
				var form_id = $select.val();

				if ( form_id == '' ) {
					return false;
				}

				var $form_to_show = $( '#gform-' + form_id );


				$( '.table_column', $form_to_show )
					.trigger( 'change' )
					.each( function () {
						var $select = $( this );
						var defaultValue = $select.attr( 'data-defaultValue' );
						var $defaultValue = $( '[name="' + defaultValue + '"]' );

						var val = $( this ).attr( 'data-value' );

						if ( typeof val === 'undefined' || val == '' ) {
							return;
						}

						$defaultValue.val( val );
					} );


				$( '.gform' ).not( $form_to_show ).hide();
				$form_to_show.show();



			}
		);

		$( '.table_column' ).on(
			'change',
			function () {
//				console.log('test');
				var $select = $( this );
				var defaultValue = $select.attr( 'data-defaultValue' );
				var $defaultValue = $( '[name="' + defaultValue + '"]' );
				var $wrapper = $select.closest( '.gform' );
				var $board_id = $( '.board_id', $wrapper );

				var field = $select.val();
				var board_id = $board_id.val();
//				console.log('#' + field + board_id);
				var $template = $( '#' + field + board_id );

				if ( $template.length == 0 ) {
					$defaultValue.empty().hide();
					return false;
				}

				var $options = $template.html();
				$defaultValue.html( $options ).show();
			}
		);


	} );
</script>

<style>
	.gform  {
		display: none;
	}
	
	#kanban_gravityforms table {
		border-collapse: collapse;
	}

	#kanban_gravityforms td {
		padding: 10px;
	}
</style>