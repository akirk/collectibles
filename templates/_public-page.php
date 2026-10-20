<?php
/**
 * Minimal public document: only the explicitly projected data is rendered.
 * No private app navigation, WP head integrations or attachment captions.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
wp_enqueue_script( 'collectibles-compare', App::get_asset_url( 'compare.js' ), array(), (string) filemtime( dirname( __DIR__ ) . '/assets/compare.js' ), true );
wp_enqueue_script( 'collectibles-cards', App::get_asset_url( 'item-cards.js' ), array(), (string) filemtime( dirname( __DIR__ ) . '/assets/item-cards.js' ), true );
wp_enqueue_style( 'collectibles-public', App::get_asset_url( 'collectibles.css' ), array(), (string) filemtime( dirname( __DIR__ ) . '/assets/collectibles.css' ) );
$coll_public_title = $coll_public_data ? $coll_public_data['title'] : __( 'Collection not available', 'collectibles' );
if ( $coll_public_data && $coll_public_data['detail'] ) {
	$coll_public_title = $coll_public_data['items'][0]['title'] . ' — ' . $coll_public_title;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="<?php echo ! $coll_public_preview && ! empty( $coll_public_data['indexable'] ) ? 'index,follow' : 'noindex,nofollow'; ?>">
	<?php if ( $coll_public_data && ! $coll_public_preview ) : ?>
		<link rel="alternate" type="application/json" href="<?php echo esc_url( add_query_arg( 'format', 'json', $coll_public_data['detail'] ? $coll_public_data['items'][0]['url'] : $coll_public_data['url'] ) ); ?>">
	<?php endif; ?>
	<title><?php echo esc_html( $coll_public_title ); ?></title>
	<?php wp_styles()->do_items( array( 'collectibles-public' ) ); ?>
</head>
<body class="coll-public">
<main class="shell">
<?php if ( $coll_public_preview ) : ?>
	<section class="notice coll-public-preview">
		<p><strong><?php echo esc_html__( 'Public preview', 'collectibles' ); ?></strong> — <?php echo esc_html__( 'Only you can see this preview. No sharing settings have been changed.', 'collectibles' ); ?></p>
		<form method="post" action="<?php echo esc_url( App::get_url( $coll_share_id . '/sharing' ) ); ?>">
			<?php wp_nonce_field( 'coll_sharing_' . $coll_share_id ); ?>
			<input type="hidden" name="coll_new_items" value="<?php echo esc_attr( $coll_share_values['new_items'] ); ?>">
			<input type="hidden" name="coll_mode" value="<?php echo esc_attr( $coll_share_values['mode'] ); ?>">
			<input type="hidden" name="coll_slug" value="<?php echo esc_attr( $coll_share_values['slug'] ); ?>">
			<input type="hidden" name="coll_description" value="<?php echo esc_attr( $coll_share_values['description'] ); ?>">

			<div class="actions">
				<button class="button button-primary" name="coll_action" value="save"><?php echo esc_html__( 'Apply sharing settings', 'collectibles' ); ?></button>
				<a class="button" href="<?php echo esc_url( App::get_url( $coll_share_id . '/sharing' ) ); ?>"><?php echo esc_html__( 'Back to sharing', 'collectibles' ); ?></a>
			</div>
		</form>
	</section>
<?php endif; ?>
<?php if ( ! $coll_public_data ) : ?>
	<section class="empty-state"><h1><?php echo esc_html__( 'Collection not available', 'collectibles' ); ?></h1><p><?php echo esc_html__( 'This shared page is not available.', 'collectibles' ); ?></p></section>
<?php else : ?>
	<header class="topbar">
		<div>
			<?php if ( $coll_public_data['detail'] ) : ?>
				<a class="crumb" href="<?php echo esc_url( $coll_public_data['url'] ); ?>"><?php echo esc_html( $coll_public_data['title'] ); ?></a>
			<?php else : ?>
				<?php if ( Sharing::directory_enabled() ) : ?>
					<a class="crumb" href="<?php echo esc_url( add_query_arg( 'view', 'shared', App::get_url() ) ); ?>"><?php echo esc_html__( 'Shared collections', 'collectibles' ); ?></a>
				<?php endif; ?>
				<p class="eyebrow"><?php echo esc_html( $coll_public_data['owner'] ); ?></p>
			<?php endif; ?>
			<h1><?php echo esc_html( $coll_public_data['detail'] ? $coll_public_data['items'][0]['title'] : $coll_public_data['title'] ); ?></h1>
			<?php if ( ! $coll_public_data['detail'] && '' !== $coll_public_data['description'] ) : ?>
				<p class="coll-public-description"><?php echo esc_html( $coll_public_data['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<p class="eyebrow"><?php echo esc_html__( 'Powered by', 'collectibles' ); ?> <a href="https://wpapps.kirk.at/apps/collectibles/" rel="noreferrer">Collectibles</a></p>
	</header>
	<?php if ( ! $coll_public_data['items'] ) : ?>
		<section class="empty-state"><p><?php echo esc_html__( 'No items are shared here yet.', 'collectibles' ); ?></p></section>
	<?php endif; ?>
	<div class="coll-public-browser">
	<?php if ( ! $coll_public_data['detail'] ) : ?>
		<input class="screen-reader-text" type="radio" name="coll_public_view" id="coll-public-gallery" checked>
		<label class="button coll-public-view" for="coll-public-gallery"><?php echo esc_html__( 'Gallery', 'collectibles' ); ?></label>
		<input class="screen-reader-text" type="radio" name="coll_public_view" id="coll-public-expanded">
		<label class="button coll-public-view" for="coll-public-expanded"><?php echo esc_html__( 'Detailed', 'collectibles' ); ?></label>
		<?php
		if ( ! $coll_public_data['detail'] && ! $coll_public_preview ) {
			$coll_local = $coll_public_data;
			require __DIR__ . '/_collection-compare.php';
		}
		?>

	<?php endif; ?>
	<div id="coll-public-items" style="--coll-thumb-ratio: <?php echo esc_attr( $coll_public_data['ratio'] ); ?>" class="<?php echo $coll_public_data['detail'] ? 'coll-public-detail' : 'item-grid'; ?>">
	<?php foreach ( $coll_public_data['items'] as $coll_public_entry ) : ?>
		<?php
		$coll_card_front = '';
		$coll_card_back  = '';
		foreach ( $coll_public_entry['photos'] as $coll_public_photo ) {
			if ( 'front' === $coll_public_photo['side'] ) {
				$coll_card_front = $coll_public_photo['thumbnail'];
			} elseif ( 'back' === $coll_public_photo['side'] ) {
				$coll_card_back = $coll_public_photo['thumbnail'];
			}
		}
		$coll_card_display = array(
			'title'          => $coll_public_entry['title'],
			'url'            => $coll_public_preview ? '#public-item-' . $coll_public_entry['slug'] : $coll_public_entry['url'],
			'ratio'          => $coll_public_data['ratio'],
			'icon'           => $coll_public_data['icon'],
			'image'          => $coll_card_front ? $coll_card_front : ( $coll_public_entry['photos'][0]['thumbnail'] ?? '' ),
			'back'           => $coll_card_front && $coll_card_back !== $coll_card_front ? $coll_card_back : '',
			'quantity'       => 1,
			'facts'          => array(),
			'collection'     => '',
			'collection_url' => '',
			'status'         => '',
			'status_class'   => '',
			'value'          => '',
			'sharing'        => array(),
			'fields'         => $coll_public_entry['fields'],
			'photos'         => $coll_public_entry['photos'],
		);
		?>
		<div id="public-item-<?php echo esc_attr( $coll_public_entry['slug'] ); ?>" class="coll-public-card">
			<?php require __DIR__ . '/_item-card-display.php'; ?>
		</div>
	<?php endforeach; ?>
	</div>
	</div>
<?php endif; ?>
</main>
<?php wp_scripts()->do_items( array( 'collectibles-cards', 'collectibles-compare' ) ); ?>
</body>
</html>
