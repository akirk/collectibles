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
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $coll_public_title ); ?></title>
	<?php wp_styles()->do_items( array( 'collectibles-public' ) ); ?>
</head>
<body class="coll-public">
<main class="shell">
<?php if ( $coll_public_preview ) : ?>
	<section class="notice coll-public-preview">
		<p><strong><?php echo esc_html__( 'Public preview', 'collectibles' ); ?></strong> — <?php echo esc_html__( 'Only you can see this preview. No sharing settings have been changed.', 'collectibles' ); ?></p>
		<form method="post" action="<?php echo esc_url( App::get_url( 'collection/' . $coll_share_id . '/sharing' ) ); ?>">
			<?php wp_nonce_field( 'coll_sharing_' . $coll_share_id ); ?>
			<input type="hidden" name="coll_mode" value="<?php echo esc_attr( $coll_share_values['mode'] ); ?>">
			<input type="hidden" name="coll_slug" value="<?php echo esc_attr( $coll_share_values['slug'] ); ?>">
			<input type="hidden" name="coll_description" value="<?php echo esc_attr( $coll_share_values['description'] ); ?>">
			<?php foreach ( $coll_share_values['item_ids'] as $coll_selected_id ) : ?>
				<input type="hidden" name="coll_item_ids[]" value="<?php echo esc_attr( $coll_selected_id ); ?>">
			<?php endforeach; ?>
			<div class="actions">
				<button class="button button-primary" name="coll_action" value="save"><?php echo esc_html__( 'Apply sharing settings', 'collectibles' ); ?></button>
				<a class="button" href="<?php echo esc_url( App::get_url( 'collection/' . $coll_share_id . '/sharing' ) ); ?>"><?php echo esc_html__( 'Back to sharing', 'collectibles' ); ?></a>
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
				<p class="eyebrow"><?php echo esc_html__( 'A shared collection', 'collectibles' ); ?></p>
			<?php endif; ?>
			<h1><?php echo esc_html( $coll_public_data['detail'] ? $coll_public_data['items'][0]['title'] : $coll_public_data['title'] ); ?></h1>
			<?php if ( ! $coll_public_data['detail'] && '' !== $coll_public_data['description'] ) : ?>
				<p class="coll-public-description"><?php echo esc_html( $coll_public_data['description'] ); ?></p>
			<?php endif; ?>
		</div>
	</header>
	<?php if ( ! $coll_public_data['items'] ) : ?>
		<section class="empty-state"><p><?php echo esc_html__( 'No items are shared here yet.', 'collectibles' ); ?></p></section>
	<?php endif; ?>
	<div class="<?php echo $coll_public_data['detail'] ? 'coll-public-detail' : 'item-grid'; ?>">
	<?php foreach ( $coll_public_data['items'] as $coll_public_entry ) : ?>
		<article class="item-card" style="--coll-thumb-ratio: <?php echo esc_attr( $coll_public_data['ratio'] ); ?>">
			<?php $coll_public_href = $coll_public_preview ? '#public-item-' . $coll_public_entry['slug'] : $coll_public_entry['url']; ?>
			<a class="item-card-thumb<?php echo $coll_public_entry['photos'] ? '' : ' is-empty'; ?>" href="<?php echo esc_url( $coll_public_href ); ?>">
				<?php if ( $coll_public_entry['photos'] ) : ?>
					<img src="<?php echo esc_url( $coll_public_data['detail'] ? $coll_public_entry['photos'][0]['image'] : $coll_public_entry['photos'][0]['thumbnail'] ); ?>" alt="<?php echo esc_attr( $coll_public_entry['title'] ); ?>" loading="lazy">
				<?php else : ?>
					<span class="kind-icon" aria-hidden="true"><?php echo esc_html( $coll_public_data['icon'] ); ?></span>
				<?php endif; ?>
			</a>
			<div class="item-card-body">
				<?php if ( ! $coll_public_data['detail'] ) : ?>
					<h2><a href="<?php echo esc_url( $coll_public_href ); ?>"><?php echo esc_html( $coll_public_entry['title'] ); ?></a></h2>
				<?php endif; ?>
				<details id="public-item-<?php echo esc_attr( $coll_public_entry['slug'] ); ?>" <?php echo $coll_public_data['detail'] ? 'open' : ''; ?>>
					<summary><?php echo esc_html__( 'Details', 'collectibles' ); ?></summary>
					<dl class="coll-public-fields">
						<?php foreach ( $coll_public_entry['fields'] as $coll_public_field ) : ?>
							<div><dt><?php echo esc_html( $coll_public_field['label'] ); ?></dt><dd><?php echo esc_html( $coll_public_field['value'] ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
					<?php foreach ( $coll_public_entry['photos'] as $coll_public_photo ) : ?>
						<figure class="coll-public-photo">
							<a href="<?php echo esc_url( $coll_public_photo['url'] ); ?>"><img src="<?php echo esc_url( $coll_public_photo['image'] ); ?>" alt="<?php echo esc_attr( $coll_public_entry['title'] ); ?>" loading="lazy"></a>
							<?php if ( $coll_public_photo['source'] ) : ?>
								<figcaption><a href="<?php echo esc_url( $coll_public_photo['source'] ); ?>"><?php echo esc_html__( 'Catalogue image', 'collectibles' ); ?></a></figcaption>
							<?php endif; ?>
						</figure>
					<?php endforeach; ?>
				</details>
			</div>
		</article>
	<?php endforeach; ?>
	</div>
<?php endif; ?>
</main>
</body>
</html>
