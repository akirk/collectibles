<?php
/**
 * Create, edit and delete an item.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$coll_route_params  = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$coll_collection_id = isset( $coll_route_params['collection_id'] ) ? absint( $coll_route_params['collection_id'] ) : absint( get_query_var( 'collection_id' ) );
$coll_item_id       = isset( $coll_route_params['id'] ) ? absint( $coll_route_params['id'] ) : absint( get_query_var( 'id' ) );
$coll_is_new        = 0 === $coll_item_id;
$coll_collection    = Collection::get( $coll_collection_id );
$coll_item          = $coll_is_new ? null : Item::get( $coll_item_id );
$coll_form_error    = '';

$coll_not_found = ! $coll_collection
	|| ( ! $coll_is_new && ( ! $coll_item || absint( $coll_item->post_parent ) !== $coll_collection_id ) );
$coll_forbidden = ! $coll_not_found && (
	$coll_is_new
		? ! current_user_can( 'edit_post', $coll_collection_id )
		: ! current_user_can( 'edit_post', $coll_item_id )
);

if ( $coll_not_found ) {
	status_header( 404 );
} elseif ( $coll_forbidden ) {
	status_header( 403 );
}

$coll_kind     = $coll_not_found ? Schema::KIND_OTHER : Collection::get_kind( $coll_collection_id );
$coll_currency = $coll_not_found ? 'EUR' : Collection::get_currency( $coll_collection_id );
$coll_fields   = Item::get_fields_for_kind( $coll_kind );
$coll_action   = isset( $_POST['coll_action'] ) ? sanitize_key( wp_unslash( $_POST['coll_action'] ) ) : '';

if ( ! $coll_not_found && ! $coll_forbidden && '' !== $coll_action ) {
	$coll_nonce = isset( $_POST['coll_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_nonce'] ) ) : '';

	if ( 'delete_item' === $coll_action && ! $coll_is_new ) {
		if ( ! wp_verify_nonce( $coll_nonce, 'coll_delete_item_' . $coll_item_id ) ) {
			$coll_form_error = __( 'The item could not be removed. Reload and try again.', 'collectibles' );
		} elseif ( ! current_user_can( 'delete_post', $coll_item_id ) ) {
			$coll_form_error = __( 'You do not have permission to remove this item.', 'collectibles' );
		} elseif ( ! wp_delete_post( $coll_item_id, true ) ) {
			$coll_form_error = __( 'The item could not be removed.', 'collectibles' );
		} else {
			wp_safe_redirect( add_query_arg( 'item_deleted', '1', App::get_url( 'collection/' . $coll_collection_id ) ) );
			exit;
		}
	} elseif ( in_array( $coll_action, array( 'create_item', 'update_item' ), true ) ) {
		$coll_expected_nonce = $coll_is_new ? 'coll_create_item_' . $coll_collection_id : 'coll_update_item_' . $coll_item_id;
		$coll_name           = isset( $_POST['coll_name'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_name'] ) ) : '';
		$coll_notes_input    = isset( $_POST['coll_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['coll_notes'] ) ) : '';
		$coll_tags_input     = isset( $_POST['coll_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_tags'] ) ) : '';

		if ( ! wp_verify_nonce( $coll_nonce, $coll_expected_nonce ) ) {
			$coll_form_error = __( 'The item could not be saved. Reload and try again.', 'collectibles' );
		} elseif ( '' === $coll_name ) {
			$coll_form_error = __( 'The item needs a name.', 'collectibles' );
		} else {
			$coll_postarr = array(
				'post_title'   => $coll_name,
				'post_content' => $coll_notes_input,
			);

			if ( $coll_is_new ) {
				$coll_postarr['post_type']   = Item::POST_TYPE;
				$coll_postarr['post_status'] = 'publish';
				$coll_postarr['post_parent'] = $coll_collection_id;
				$coll_postarr['post_author'] = get_current_user_id();

				$coll_saved_id = wp_insert_post( $coll_postarr, true );
			} else {
				$coll_postarr['ID'] = $coll_item_id;

				$coll_saved_id = wp_update_post( $coll_postarr, true );
			}

			if ( is_wp_error( $coll_saved_id ) ) {
				$coll_form_error = $coll_saved_id->get_error_message();
			} else {
				$coll_saved_id = absint( $coll_saved_id );

				Item::save_values( $coll_saved_id, $coll_kind, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Item::save_values() sanitizes each field by its definition.

				$coll_tag_names = array_values(
					array_filter(
						array_map( 'trim', explode( ',', $coll_tags_input ) )
					)
				);

				wp_set_object_terms( $coll_saved_id, $coll_tag_names, Item::TAXONOMY, false );

				// Remove the photos that were ticked for removal.
				$coll_removals = isset( $_POST['coll_remove_photo'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['coll_remove_photo'] ) ) : array();

				foreach ( $coll_removals as $coll_removal_id ) {
					if ( $coll_removal_id && absint( get_post_field( 'post_parent', $coll_removal_id ) ) === $coll_saved_id ) {
						wp_delete_attachment( $coll_removal_id, true );
					}
				}

				$coll_upload_error = Item::handle_photo_uploads( $coll_saved_id );

				// Pick the primary photo, falling back to whatever is left.
				$coll_primary = isset( $_POST['coll_primary_photo'] ) ? absint( wp_unslash( $_POST['coll_primary_photo'] ) ) : 0;
				$coll_photos  = Item::get_photo_ids( $coll_saved_id );

				if ( $coll_primary && in_array( $coll_primary, $coll_photos, true ) ) {
					set_post_thumbnail( $coll_saved_id, $coll_primary );
				} elseif ( ! in_array( absint( get_post_thumbnail_id( $coll_saved_id ) ), $coll_photos, true ) ) {
					if ( empty( $coll_photos ) ) {
						delete_post_thumbnail( $coll_saved_id );
					} else {
						set_post_thumbnail( $coll_saved_id, $coll_photos[0] );
					}
				}

				if ( '' !== $coll_upload_error ) {
					$coll_form_error = $coll_upload_error;
					$coll_item_id    = $coll_saved_id;
					$coll_is_new     = false;
					$coll_item       = Item::get( $coll_item_id );
				} else {
					wp_safe_redirect(
						add_query_arg(
							'saved',
							'1',
							App::get_url( 'collection/' . $coll_collection_id . '/item/' . $coll_saved_id )
						)
					);
					exit;
				}
			}
		}
	}
}

$coll_name_value  = $coll_item ? get_the_title( $coll_item ) : '';
$coll_notes_value = $coll_item ? $coll_item->post_content : '';
$coll_tags_value  = $coll_item ? implode( ', ', wp_list_pluck( Item::get_tags( $coll_item_id ), 'name' ) ) : '';
$coll_values      = $coll_item ? Item::get_values( $coll_item_id, $coll_kind ) : array();
$coll_photo_ids   = $coll_item ? Item::get_photo_ids( $coll_item_id ) : array();
$coll_primary_id  = $coll_item ? absint( get_post_thumbnail_id( $coll_item_id ) ) : 0;

if ( '' !== $coll_form_error ) {
	// Keep what was typed so a validation error does not throw the form away.
	$coll_name_value  = isset( $_POST['coll_name'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_name'] ) ) : $coll_name_value;
	$coll_notes_value = isset( $_POST['coll_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['coll_notes'] ) ) : $coll_notes_value;
	$coll_tags_value  = isset( $_POST['coll_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_tags'] ) ) : $coll_tags_value;

	foreach ( $coll_fields as $coll_field_def ) {
		$coll_input_name = 'coll_field_' . $coll_field_def['key'];

		if ( isset( $_POST[ $coll_input_name ] ) ) {
			$coll_values[ $coll_field_def['key'] ] = Item::sanitize_field_value(
				$coll_field_def,
				wp_unslash( $_POST[ $coll_input_name ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Item::sanitize_field_value() sanitizes by field definition.
			);
		}
	}
}

$coll_page_title = $coll_is_new
	? sprintf(
		/* translators: %s: singular item noun, e.g. "Coin" */
		__( 'Add %s', 'collectibles' ),
		Schema::get_item_noun( $coll_kind )
	)
	: __( 'Edit item', 'collectibles' );
$coll_form_url    = $coll_is_new
	? App::get_url( 'collection/' . $coll_collection_id . '/item/new' )
	: App::get_url( 'collection/' . $coll_collection_id . '/item/' . $coll_item_id . '/edit' );
$coll_cancel_url  = $coll_is_new
	? App::get_url( 'collection/' . $coll_collection_id )
	: App::get_url( 'collection/' . $coll_collection_id . '/item/' . $coll_item_id );
$coll_shell_class = 'shell shell-narrow';

require __DIR__ . '/_head.php';
?>

		<?php if ( $coll_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Item not found', 'collectibles' ); ?></h1>
				<p><?php echo esc_html__( 'The requested item is not available.', 'collectibles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to collections', 'collectibles' ); ?></a></p>
			</section>
		<?php elseif ( $coll_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access denied', 'collectibles' ); ?></h1>
				<p><?php echo esc_html__( 'You do not have permission to edit items in this collection.', 'collectibles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to collections', 'collectibles' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id ) ); ?>">
						<?php echo esc_html( get_the_title( $coll_collection ) ); ?>
					</a>
					<h1><?php echo esc_html( $coll_page_title ); ?></h1>
				</div>
			</header>

			<?php if ( '' !== $coll_form_error ) : ?>
				<div class="notice notice-error"><?php echo esc_html( $coll_form_error ); ?></div>
			<?php endif; ?>

			<form class="panel" method="post" action="<?php echo esc_url( $coll_form_url ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="coll_action" value="<?php echo esc_attr( $coll_is_new ? 'create_item' : 'update_item' ); ?>">
				<?php wp_nonce_field( $coll_is_new ? 'coll_create_item_' . $coll_collection_id : 'coll_update_item_' . $coll_item_id, 'coll_nonce' ); ?>

				<div class="field">
					<label for="coll_name"><?php echo esc_html__( 'Name', 'collectibles' ); ?></label>
					<input id="coll_name" name="coll_name" type="text" value="<?php echo esc_attr( $coll_name_value ); ?>" required autofocus>
				</div>

				<div class="field-grid">
					<?php
					foreach ( $coll_fields as $coll_field ) {
						$coll_field_value    = $coll_values[ $coll_field['key'] ] ?? '';
						$coll_field_currency = $coll_currency;

						require __DIR__ . '/_field-input.php';
					}
					?>
				</div>

				<div class="field">
					<label for="coll_tags"><?php echo esc_html__( 'Tags', 'collectibles' ); ?></label>
					<input id="coll_tags" name="coll_tags" type="text" value="<?php echo esc_attr( $coll_tags_value ); ?>" placeholder="<?php echo esc_attr__( 'Comma separated', 'collectibles' ); ?>">
				</div>

				<div class="field">
					<label for="coll_notes"><?php echo esc_html__( 'Notes', 'collectibles' ); ?></label>
					<textarea id="coll_notes" name="coll_notes" rows="5"><?php echo esc_textarea( $coll_notes_value ); ?></textarea>
				</div>

				<?php if ( current_user_can( 'upload_files' ) ) : ?>
					<div class="field">
						<label for="coll_photos"><?php echo esc_html__( 'Photos', 'collectibles' ); ?></label>
						<input id="coll_photos" name="coll_photos[]" type="file" accept="image/*" multiple>
						<p class="field-hint"><?php echo esc_html__( 'Front, back, detail — whatever helps you recognise the piece.', 'collectibles' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $coll_photo_ids ) ) : ?>
					<fieldset class="field">
						<legend><?php echo esc_html__( 'Existing photos', 'collectibles' ); ?></legend>
						<div class="photo-manager">
							<?php foreach ( $coll_photo_ids as $coll_photo_id ) : ?>
								<div class="photo-manager-item">
									<?php echo wp_kses_post( wp_get_attachment_image( $coll_photo_id, 'thumbnail', false, array( 'alt' => '' ) ) ); ?>
									<label>
										<input type="radio" name="coll_primary_photo" value="<?php echo esc_attr( $coll_photo_id ); ?>" <?php checked( $coll_primary_id, $coll_photo_id ); ?>>
										<?php echo esc_html__( 'Main', 'collectibles' ); ?>
									</label>
									<label>
										<input type="checkbox" name="coll_remove_photo[]" value="<?php echo esc_attr( $coll_photo_id ); ?>">
										<?php echo esc_html__( 'Remove', 'collectibles' ); ?>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endif; ?>

				<div class="form-actions">
					<button class="button button-primary" type="submit">
						<?php echo esc_html( $coll_is_new ? __( 'Add to collection', 'collectibles' ) : __( 'Save item', 'collectibles' ) ); ?>
					</button>
					<a class="button button-quiet" href="<?php echo esc_url( $coll_cancel_url ); ?>"><?php echo esc_html__( 'Cancel', 'collectibles' ); ?></a>
				</div>
			</form>

			<?php if ( ! $coll_is_new && current_user_can( 'delete_post', $coll_item_id ) ) : ?>
				<form class="panel panel-danger" method="post" action="<?php echo esc_url( $coll_form_url ); ?>">
					<input type="hidden" name="coll_action" value="delete_item">
					<?php wp_nonce_field( 'coll_delete_item_' . $coll_item_id, 'coll_nonce' ); ?>
					<h2><?php echo esc_html__( 'Delete item', 'collectibles' ); ?></h2>
					<p><?php echo esc_html__( 'This removes the item and its photos.', 'collectibles' ); ?></p>
					<button
						class="button button-danger"
						type="submit"
						onclick="return confirm('<?php echo esc_js( __( 'Delete this item?', 'collectibles' ) ); ?>');"
					>
						<?php echo esc_html__( 'Delete item', 'collectibles' ); ?>
					</button>
				</form>
			<?php endif; ?>
		<?php endif; ?>

<?php
require __DIR__ . '/_foot.php';
