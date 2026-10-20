<?php
/**
 * Import an exported collection CSV.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$coll_route_params  = $wp_app_route['params'] ?? array();
$coll_collection_id = absint( $coll_route_params['id'] ?? get_query_var( 'id' ) );
$coll_collection    = Collection::get( $coll_collection_id );
if ( ! $coll_collection ) {
	wp_die( esc_html__( 'The requested collection is not available.', 'collectibles' ), '', array( 'response' => 404 ) );
}
if ( ! current_user_can( 'edit_post', $coll_collection_id ) || ! current_user_can( 'edit_posts' ) ) {
	wp_die( esc_html__( 'You do not have permission to import into this collection.', 'collectibles' ), '', array( 'response' => 403 ) );
}

$coll_error = '';
$coll_count = 0;
if ( 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
	$coll_nonce = isset( $_POST['coll_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $coll_nonce, 'coll_import_' . $coll_collection_id ) ) {
		$coll_error = __( 'The CSV could not be imported. Reload and try again.', 'collectibles' );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- PHP temporary upload path checked with is_uploaded_file().
	} elseif ( ! isset( $_FILES['coll_csv']['error'], $_FILES['coll_csv']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['coll_csv']['error'] || ! is_string( $_FILES['coll_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['coll_csv']['tmp_name'] ) ) {
		$coll_error = __( 'Choose a CSV file that fits within the upload limit.', 'collectibles' );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified PHP temporary upload path.
	} elseif ( filesize( $_FILES['coll_csv']['tmp_name'] ) > min( wp_max_upload_size(), 10 * MB_IN_BYTES ) ) {
		$coll_error = __( 'The CSV is too large. The import limit is 10 MB or the site upload limit, whichever is smaller.', 'collectibles' );
	} else {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified PHP upload, read only as CSV data.
		$coll_document = file_get_contents( $_FILES['coll_csv']['tmp_name'] );
		$coll_result   = false === $coll_document ? new \WP_Error( 'csv_read', __( 'The uploaded CSV could not be read.', 'collectibles' ) ) : Csv::import_collection( $coll_document, $coll_collection_id );
		if ( is_wp_error( $coll_result ) ) {
			$coll_error = $coll_result->get_error_message();
		} else {
			// Use a redirect so refreshing the result never repeats an import.
			wp_safe_redirect( add_query_arg( 'imported', $coll_result, App::get_url( $coll_collection_id . '/import' ) ) );
			exit;
		}
	}
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only result of the import redirect.
$coll_count       = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
$coll_page_title  = __( 'Import CSV', 'collectibles' );
$coll_shell_class = 'shell shell-narrow';
require __DIR__ . '/_head.php';
?>
<header class="topbar">
	<div>
		<a class="crumb" href="<?php echo esc_url( App::get_url( $coll_collection_id ) ); ?>"><?php echo esc_html( get_the_title( $coll_collection ) ); ?></a>
		<h1><?php echo esc_html( $coll_page_title ); ?></h1>
	</div>
</header>
<?php if ( $coll_error ) : ?>
	<div class="notice notice-error" role="alert"><?php echo esc_html( $coll_error ); ?></div>
<?php endif; ?>
<?php if ( $coll_count ) : ?>
	<div class="notice" role="status">
		<?php
		/* translators: %s: number of imported items. */
		printf( esc_html( _n( '%s item imported.', '%s items imported.', $coll_count, 'collectibles' ) ), esc_html( number_format_i18n( $coll_count ) ) );
		?>
		<a href="<?php echo esc_url( App::get_url( $coll_collection_id ) ); ?>"><?php echo esc_html__( 'View collection', 'collectibles' ); ?></a>
	</div>
<?php endif; ?>
<form class="panel" method="post" enctype="multipart/form-data" action="<?php echo esc_url( App::get_url( $coll_collection_id . '/import' ) ); ?>">
	<?php wp_nonce_field( 'coll_import_' . $coll_collection_id, 'coll_nonce' ); ?>
	<p><?php echo esc_html__( 'Choose a CSV exported from a collection of the same kind, currency and language. Items, lots, tags and notes will be added to this collection. Photos are not included in CSV exports.', 'collectibles' ); ?></p>
	<p><?php echo esc_html__( 'Existing items stay as they are. Importing the same file again creates duplicates. Imported items are private. In older exports without an Item ID column, rows with identical item details are combined as lots of one item.', 'collectibles' ); ?></p>
	<div class="field">
		<label for="coll_csv"><?php echo esc_html__( 'CSV file', 'collectibles' ); ?></label>
		<input id="coll_csv" name="coll_csv" type="file" accept=".csv,text/csv" required>
	</div>
	<div class="form-actions">
		<button class="button button-primary" type="submit"><?php echo esc_html__( 'Import items', 'collectibles' ); ?></button>
		<a class="button button-quiet" href="<?php echo esc_url( App::get_url( $coll_collection_id ) ); ?>"><?php echo esc_html__( 'Cancel', 'collectibles' ); ?></a>
	</div>
</form>
<?php require __DIR__ . '/_foot.php'; ?>
