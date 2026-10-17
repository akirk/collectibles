<?php
/**
 * Fill missing photos one at a time or import catalogue images in sequence.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;
$coll_params        = $wp_app_route['params'] ?? array();
$coll_collection_id = absint( $coll_params['id'] ?? get_query_var( 'id' ) );
$coll_collection    = Collection::get( $coll_collection_id );
if ( ! $coll_collection || ! current_user_can( 'edit_post', $coll_collection_id ) || ! current_user_can( 'upload_files' ) ) {
	wp_die( esc_html__( 'This collection is not available for photo uploads.', 'collectibles' ), '', array( 'response' => 403 ) );
}
$coll_catalog = Rebrickable::supports_kind( Collection::get_kind( $coll_collection_id ) );
$coll_error   = '';
$coll_preview = array();
if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
	check_admin_referer( 'coll_photos_' . $coll_collection_id );
	$coll_item_id = absint( $_POST['coll_item_id'] ?? 0 );
	$coll_item    = Item::get( $coll_item_id );
	if ( ! $coll_item || (int) $coll_item->post_parent !== $coll_collection_id || ! current_user_can( 'edit_post', $coll_item_id ) ) {
		wp_die( esc_html__( 'This item is not available.', 'collectibles' ), '', array( 'response' => 403 ) );
	}
	$coll_action = sanitize_key( wp_unslash( $_POST['coll_action'] ?? '' ) );
	if ( 'preview' === $coll_action && $coll_catalog ) {
		$coll_url = Rebrickable::get_photo_url( $coll_item_id );
		if ( is_wp_error( $coll_url ) ) {
			$coll_error = $coll_url->get_error_message();
		} else {
			$coll_preview[ $coll_item_id ] = $coll_url;
		}
	} else {
		if ( Item::get_photo_ids( $coll_item_id ) ) {
			$coll_result = 0;
		} elseif ( 'import' === $coll_action && $coll_catalog ) {
			$coll_result = Rebrickable::import_photo( $coll_item_id );
		} elseif ( 'upload' === $coll_action ) {
			$coll_result = Item::handle_side_upload( $coll_item_id, Item::FRONT_META_KEY );
			if ( '' !== $coll_result ) {
				$coll_result = new \WP_Error( 'coll_photo_upload', $coll_result );
			} elseif ( ! Item::get_photo_ids( $coll_item_id ) ) {
				$coll_result = new \WP_Error( 'coll_photo_upload', __( 'Choose a photo to upload.', 'collectibles' ) );
			}
		} else {
			$coll_result = new \WP_Error( 'coll_photo_action', __( 'Choose a photo action.', 'collectibles' ) );
		}
		if ( ! empty( $_POST['coll_batch'] ) ) {
			if ( is_wp_error( $coll_result ) ) {
				wp_send_json_error( $coll_result->get_error_message() );
			}
			wp_send_json_success();
		}
		if ( is_wp_error( $coll_result ) ) {
			$coll_error = $coll_result->get_error_message();
		} else {
			wp_safe_redirect( App::get_url( 'collection/' . $coll_collection_id . '/photos' ) );
			exit;
		}
	}
}
$coll_missing    = array_filter(
	Item::query( array( 'collection' => $coll_collection_id ) ),
	static function ( $item ) {
		return ! Item::get_photo_ids( $item->ID );
	}
);
$coll_page_title = __( 'Missing photos', 'collectibles' );
require __DIR__ . '/_head.php';
?>
<header class="topbar">
	<div>
		<a class="crumb" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id ) ); ?>"><?php echo esc_html( $coll_collection->post_title ); ?></a>
		<h1><?php echo esc_html( $coll_page_title ); ?></h1>
		<p><?php echo esc_html__( 'Add photos to the items below. Existing photos are preserved.', 'collectibles' ); ?></p>
	</div>
	<?php if ( $coll_catalog && $coll_missing ) : ?>
		<button type="button" class="button" id="coll-import-all"><?php echo esc_html__( 'Use catalogue images for all', 'collectibles' ); ?></button>
	<?php endif; ?>
</header>
<p id="coll-photo-progress" role="status" aria-live="polite"></p>
<?php if ( $coll_error ) : ?>
	<p class="notice"><?php echo esc_html( $coll_error ); ?></p>
<?php endif; ?>
<?php if ( ! $coll_missing ) : ?>
	<p class="notice"><?php echo esc_html__( 'Every item has a photo.', 'collectibles' ); ?></p>
<?php endif; ?>
<div class="coll-photo-batch">
<?php foreach ( $coll_missing as $coll_item ) : ?>
	<section class="coll-photo-row">
		<h2><a href="<?php echo esc_url( App::get_url( 'collection/' . $coll_collection_id . '/item/' . $coll_item->ID ) ); ?>"><?php echo esc_html( $coll_item->post_title ); ?></a></h2>
		<?php if ( isset( $coll_preview[ $coll_item->ID ] ) ) : ?>
			<figure>
				<img class="coll-catalog-preview" src="<?php echo esc_url( $coll_preview[ $coll_item->ID ] ); ?>" alt="<?php echo esc_attr( $coll_item->post_title ); ?>">
				<figcaption><?php echo esc_html__( 'Rebrickable catalogue image', 'collectibles' ); ?></figcaption>
			</figure>
		<?php endif; ?>
		<form method="post" enctype="multipart/form-data" class="coll-photo-form">
			<?php wp_nonce_field( 'coll_photos_' . $coll_collection_id ); ?>
			<input type="hidden" name="coll_item_id" value="<?php echo esc_attr( $coll_item->ID ); ?>">
			<label for="coll-photo-<?php echo esc_attr( $coll_item->ID ); ?>"><?php echo esc_html__( 'Your photo', 'collectibles' ); ?></label>
			<input id="coll-photo-<?php echo esc_attr( $coll_item->ID ); ?>" type="file" name="coll_photo_front" accept="image/*">
			<div class="actions">
				<button class="button" name="coll_action" value="upload"><?php echo esc_html__( 'Upload photo', 'collectibles' ); ?></button>
				<?php if ( $coll_catalog ) : ?>
					<button class="button" name="coll_action" value="preview"><?php echo esc_html__( 'Preview catalogue image', 'collectibles' ); ?></button>
					<button class="button" name="coll_action" value="import"><?php echo esc_html__( 'Use catalogue image', 'collectibles' ); ?></button>
				<?php endif; ?>
			</div>
		</form>
	</section>
<?php endforeach; ?>
</div>
<script>
(() => {
	const button = document.getElementById('coll-import-all');
	if (!button) return;
	const progress = document.getElementById('coll-photo-progress');
	button.addEventListener('click', async () => {
		const forms = Array.from(document.querySelectorAll('.coll-photo-form'));
		document.querySelectorAll('.coll-photo-form button, .coll-photo-form input').forEach(input => input.disabled = true);
		button.disabled = true;
		let completed = 0;
		try {
			for (const form of forms) {
				// Disabled controls are omitted from FormData; read the original values explicitly.
				const data = new FormData();
				data.set('_wpnonce', form.querySelector('[name="_wpnonce"]').value);
				data.set('coll_item_id', form.querySelector('[name="coll_item_id"]').value);
				data.set('coll_action', 'import');
				data.set('coll_batch', '1');
				const response = await fetch(window.location.href, {method: 'POST', body: data});
				if (!response.ok) throw new Error(<?php echo wp_json_encode( __( 'Import stopped. Reload to continue with the remaining items.', 'collectibles' ) ); ?>);
				const result = await response.json();
				if (!result.success) throw new Error(result.data);
				completed++;
				form.closest('.coll-photo-row').remove();
				progress.textContent = completed + ' / ' + forms.length;
				await new Promise(resolve => setTimeout(resolve, 1200));
			}
			window.location.reload();
		} catch (error) {
			progress.textContent = error.message;
			document.querySelectorAll('.coll-photo-form button, .coll-photo-form input').forEach(input => input.disabled = false);
			button.disabled = false;
		}
	});
})();
</script>
<?php require __DIR__ . '/_foot.php'; ?>
