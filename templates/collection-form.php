<?php
/**
 * Create, edit and delete a collection.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$coll_route_params  = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$coll_collection_id = isset( $coll_route_params['id'] ) ? absint( $coll_route_params['id'] ) : absint( get_query_var( 'id' ) );
$coll_is_new        = 0 === $coll_collection_id;
$coll_collection    = $coll_is_new ? null : Collection::get( $coll_collection_id );
$coll_form_error    = '';

$coll_not_found = ! $coll_is_new && ! $coll_collection;
$coll_forbidden = ! $coll_not_found && (
	$coll_is_new
		? ! current_user_can( 'edit_posts' )
		: ! current_user_can( 'edit_post', $coll_collection_id )
);

if ( $coll_not_found ) {
	status_header( 404 );
} elseif ( $coll_forbidden ) {
	status_header( 403 );
}

$coll_action = isset( $_POST['coll_action'] ) ? sanitize_key( wp_unslash( $_POST['coll_action'] ) ) : '';

if ( ! $coll_not_found && ! $coll_forbidden && '' !== $coll_action ) {
	$coll_nonce = isset( $_POST['coll_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_nonce'] ) ) : '';

	if ( 'delete_collection' === $coll_action && ! $coll_is_new ) {
		if ( ! wp_verify_nonce( $coll_nonce, 'coll_delete_collection_' . $coll_collection_id ) ) {
			$coll_form_error = __( 'The collection could not be removed. Reload and try again.', 'collectibles' );
		} elseif ( ! current_user_can( 'delete_post', $coll_collection_id ) ) {
			$coll_form_error = __( 'You do not have permission to remove this collection.', 'collectibles' );
		} elseif ( ! wp_delete_post( $coll_collection_id, true ) ) {
			$coll_form_error = __( 'The collection could not be removed.', 'collectibles' );
		} else {
			wp_safe_redirect( add_query_arg( 'deleted', '1', App::get_url() ) );
			exit;
		}
	} elseif ( in_array( $coll_action, array( 'create_collection', 'update_collection' ), true ) ) {
		$coll_expected_nonce = $coll_is_new ? 'coll_create_collection' : 'coll_update_collection_' . $coll_collection_id;
		$coll_name           = isset( $_POST['coll_name'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_name'] ) ) : '';
		$coll_kind_input     = isset( $_POST['coll_kind'] ) ? Schema::sanitize_kind( sanitize_text_field( wp_unslash( $_POST['coll_kind'] ) ) ) : Schema::KIND_OTHER;
		$coll_currency_input = isset( $_POST['coll_currency'] ) ? Collection::sanitize_currency( sanitize_text_field( wp_unslash( $_POST['coll_currency'] ) ) ) : '';
		$coll_notes_input    = isset( $_POST['coll_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['coll_notes'] ) ) : '';

		if ( '' === $coll_currency_input ) {
			$coll_currency_input = 'EUR';
		}

		if ( ! wp_verify_nonce( $coll_nonce, $coll_expected_nonce ) ) {
			$coll_form_error = __( 'The collection could not be saved. Reload and try again.', 'collectibles' );
		} elseif ( '' === $coll_name ) {
			$coll_form_error = __( 'The collection needs a name.', 'collectibles' );
		} else {
			$coll_postarr = array(
				'post_title'   => $coll_name,
				'post_content' => $coll_notes_input,
			);

			if ( $coll_is_new ) {
				$coll_postarr['post_type']   = Collection::POST_TYPE;
				$coll_postarr['post_status'] = 'publish';
				$coll_postarr['post_author'] = get_current_user_id();

				$coll_saved_id = wp_insert_post( $coll_postarr, true );
			} else {
				$coll_postarr['ID'] = $coll_collection_id;

				$coll_saved_id = wp_update_post( $coll_postarr, true );
			}

			if ( is_wp_error( $coll_saved_id ) ) {
				$coll_form_error = $coll_saved_id->get_error_message();
			} else {
				$coll_saved_id = absint( $coll_saved_id );

				update_post_meta( $coll_saved_id, Collection::KIND_META_KEY, $coll_kind_input );
				update_post_meta( $coll_saved_id, Collection::CURRENCY_META_KEY, $coll_currency_input );

				wp_safe_redirect( add_query_arg( 'saved', '1', App::get_url( 'collection/' . $coll_saved_id ) ) );
				exit;
			}
		}
	}
}

if ( ! $coll_is_new && ! $coll_not_found ) {
	$coll_collection = Collection::get( $coll_collection_id );
}

$coll_name_value     = $coll_collection ? get_the_title( $coll_collection ) : '';
$coll_notes_value    = $coll_collection ? $coll_collection->post_content : '';
$coll_kind_value     = $coll_collection ? Collection::get_kind( $coll_collection_id ) : Schema::KIND_COINS;
$coll_currency_value = $coll_collection ? Collection::get_currency( $coll_collection_id ) : 'EUR';

if ( '' !== $coll_form_error ) {
	// Keep what was typed so a validation error does not throw the form away.
	$coll_name_value     = isset( $_POST['coll_name'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_name'] ) ) : $coll_name_value;
	$coll_notes_value    = isset( $_POST['coll_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['coll_notes'] ) ) : $coll_notes_value;
	$coll_kind_value     = isset( $_POST['coll_kind'] ) ? Schema::sanitize_kind( sanitize_text_field( wp_unslash( $_POST['coll_kind'] ) ) ) : $coll_kind_value;
	$coll_currency_value = isset( $_POST['coll_currency'] ) ? Collection::sanitize_currency( sanitize_text_field( wp_unslash( $_POST['coll_currency'] ) ) ) : $coll_currency_value;
}

$coll_page_title  = $coll_is_new ? __( 'New collection', 'collectibles' ) : __( 'Edit collection', 'collectibles' );
$coll_form_url    = $coll_is_new ? App::get_url( 'collection/new' ) : App::get_url( 'collection/' . $coll_collection_id . '/edit' );
$coll_shell_class = 'shell shell-narrow';

require __DIR__ . '/_head.php';
?>

		<?php if ( $coll_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Collection not found', 'collectibles' ); ?></h1>
				<p><?php echo esc_html__( 'The requested collection is not available.', 'collectibles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to collections', 'collectibles' ); ?></a></p>
			</section>
		<?php elseif ( $coll_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access denied', 'collectibles' ); ?></h1>
				<p><?php echo esc_html__( 'You do not have permission to edit this collection.', 'collectibles' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to collections', 'collectibles' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( $coll_is_new ? App::get_url() : App::get_url( 'collection/' . $coll_collection_id ) ); ?>">
						<?php echo esc_html( $coll_is_new ? __( 'Collections', 'collectibles' ) : get_the_title( $coll_collection ) ); ?>
					</a>
					<h1><?php echo esc_html( $coll_page_title ); ?></h1>
				</div>
			</header>

			<?php if ( '' !== $coll_form_error ) : ?>
				<div class="notice notice-error"><?php echo esc_html( $coll_form_error ); ?></div>
			<?php endif; ?>

			<form class="panel" method="post" action="<?php echo esc_url( $coll_form_url ); ?>">
				<input type="hidden" name="coll_action" value="<?php echo esc_attr( $coll_is_new ? 'create_collection' : 'update_collection' ); ?>">
				<?php wp_nonce_field( $coll_is_new ? 'coll_create_collection' : 'coll_update_collection_' . $coll_collection_id, 'coll_nonce' ); ?>

				<div class="field">
					<label for="coll_name"><?php echo esc_html__( 'Name', 'collectibles' ); ?></label>
					<input id="coll_name" name="coll_name" type="text" value="<?php echo esc_attr( $coll_name_value ); ?>" required autofocus>
				</div>

				<fieldset class="field">
					<legend><?php echo esc_html__( 'What is in it?', 'collectibles' ); ?></legend>
					<p class="field-hint"><?php echo esc_html__( 'This decides which fields the items in this collection get.', 'collectibles' ); ?></p>
					<div class="kind-picker">
						<?php foreach ( Schema::get_kinds() as $coll_kind_slug => $coll_kind_def ) : ?>
							<label class="kind-option">
								<input type="radio" name="coll_kind" value="<?php echo esc_attr( $coll_kind_slug ); ?>" <?php checked( $coll_kind_value, $coll_kind_slug ); ?>>
								<span class="kind-option-icon" aria-hidden="true"><?php echo esc_html( $coll_kind_def['icon'] ); ?></span>
								<span class="kind-option-label"><?php echo esc_html( $coll_kind_def['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<?php if ( ! $coll_is_new ) : ?>
						<p class="field-hint"><?php echo esc_html__( 'Changing this swaps the fields shown on the items. Values already recorded under the old fields are kept, but hidden until you switch back.', 'collectibles' ); ?></p>
					<?php endif; ?>
				</fieldset>

				<div class="field field-short">
					<label for="coll_currency"><?php echo esc_html__( 'Currency', 'collectibles' ); ?></label>
					<input id="coll_currency" name="coll_currency" type="text" value="<?php echo esc_attr( $coll_currency_value ); ?>" maxlength="3" size="3" pattern="[A-Za-z]{3}">
					<p class="field-hint"><?php echo esc_html__( 'Three-letter code used for prices in this collection, e.g. EUR.', 'collectibles' ); ?></p>
				</div>

				<div class="field">
					<label for="coll_notes"><?php echo esc_html__( 'Notes', 'collectibles' ); ?></label>
					<textarea id="coll_notes" name="coll_notes" rows="4"><?php echo esc_textarea( $coll_notes_value ); ?></textarea>
				</div>

				<div class="form-actions">
					<button class="button button-primary" type="submit">
						<?php echo esc_html( $coll_is_new ? __( 'Create collection', 'collectibles' ) : __( 'Save collection', 'collectibles' ) ); ?>
					</button>
					<a class="button button-quiet" href="<?php echo esc_url( $coll_is_new ? App::get_url() : App::get_url( 'collection/' . $coll_collection_id ) ); ?>">
						<?php echo esc_html__( 'Cancel', 'collectibles' ); ?>
					</a>
				</div>
			</form>

			<?php if ( ! $coll_is_new && current_user_can( 'delete_post', $coll_collection_id ) ) : ?>
				<form class="panel panel-danger" method="post" action="<?php echo esc_url( $coll_form_url ); ?>">
					<input type="hidden" name="coll_action" value="delete_collection">
					<?php wp_nonce_field( 'coll_delete_collection_' . $coll_collection_id, 'coll_nonce' ); ?>
					<h2><?php echo esc_html__( 'Delete collection', 'collectibles' ); ?></h2>
					<p><?php echo esc_html__( 'This removes the collection, every item in it, and the photos of those items.', 'collectibles' ); ?></p>
					<button
						class="button button-danger"
						type="submit"
						onclick="return confirm('<?php echo esc_js( __( 'Delete this collection and everything in it?', 'collectibles' ) ); ?>');"
					>
						<?php echo esc_html__( 'Delete collection', 'collectibles' ); ?>
					</button>
				</form>
			<?php endif; ?>
		<?php endif; ?>

<?php
require __DIR__ . '/_foot.php';
