<?php
/**
 * Shared card layout. Receives prepared display data only; never reads item metadata.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="item-card" style="--coll-thumb-ratio: <?php echo esc_attr( $coll_card_display['ratio'] ); ?>">
	<div class="item-card-image">
		<?php if ( $coll_card_display['image'] && $coll_card_display['back'] ) : ?>
			<button type="button" class="item-card-thumb coll-card-flip" data-coll-flip data-coll-front="<?php echo esc_url( $coll_card_display['image'] ); ?>" data-coll-back="<?php echo esc_url( $coll_card_display['back'] ); ?>" data-coll-show-front="<?php echo esc_attr__( 'Show front', 'collectibles' ); ?>" data-coll-show-back="<?php echo esc_attr__( 'Show back', 'collectibles' ); ?>" data-coll-title="<?php echo esc_attr( $coll_card_display['title'] ); ?>" aria-label="<?php echo esc_attr( $coll_card_display['title'] . ': ' . __( 'Show back', 'collectibles' ) ); ?>" aria-pressed="false" title="<?php echo esc_attr__( 'Show back', 'collectibles' ); ?>" disabled>
		<?php else : ?>
			<a class="item-card-thumb<?php echo $coll_card_display['image'] ? '' : ' is-empty'; ?>" href="<?php echo esc_url( $coll_card_display['url'] ); ?>" aria-hidden="true" tabindex="-1">
		<?php endif; ?>
			<?php if ( $coll_card_display['image'] ) : ?>
				<img src="<?php echo esc_url( $coll_card_display['image'] ); ?>" alt="" loading="lazy">
			<?php else : ?>
				<span class="kind-icon" aria-hidden="true"><?php echo esc_html( $coll_card_display['icon'] ); ?></span>
			<?php endif; ?>
			<?php if ( $coll_card_display['quantity'] > 1 ) : ?>
				<span class="qty-badge">&times;<?php echo esc_html( number_format_i18n( $coll_card_display['quantity'] ) ); ?></span>
			<?php endif; ?>
		<?php if ( $coll_card_display['image'] && $coll_card_display['back'] ) : ?>
			</button>
		<?php else : ?>
			</a>
		<?php endif; ?>
	</div>
	<div class="item-card-body">
		<div class="item-card-heading">
			<h3><a href="<?php echo esc_url( $coll_card_display['url'] ); ?>"><?php echo esc_html( $coll_card_display['title'] ); ?></a></h3>
			<?php if ( ! empty( $coll_card_display['year'] ) ) : ?>
				<span class="item-card-year"><?php echo esc_html( $coll_card_display['year'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $coll_card_display['sharing'] ) ) : ?>
				<?php $coll_card_sharing = $coll_card_display['sharing']; ?>
				<button type="button" class="coll-share-toggle" data-coll-share-item="<?php echo esc_attr( $coll_card_sharing['item_id'] ); ?>" data-coll-share-collection="<?php echo esc_attr( $coll_card_sharing['collection_id'] ); ?>" data-coll-share-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-coll-share-nonce="<?php echo esc_attr( $coll_card_sharing['nonce'] ); ?>" data-coll-share-saving="<?php echo esc_attr__( 'Saving…', 'collectibles' ); ?>" data-coll-share-unknown="<?php echo esc_attr__( 'Check sharing', 'collectibles' ); ?>" data-coll-share-error="<?php echo esc_attr__( 'Sharing could not be saved. Reload to check the current state and try again.', 'collectibles' ); ?>" data-coll-share-title="<?php echo esc_attr( $coll_card_display['title'] ); ?>" aria-pressed="<?php echo $coll_card_sharing['selected'] ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $coll_card_display['title'] . ': ' . $coll_card_sharing['action'] ); ?>" title="<?php echo esc_attr( $coll_card_sharing['selected'] ? $coll_card_sharing['message'] : $coll_card_sharing['action'] ); ?>" <?php disabled( ! $coll_card_sharing['eligible'] && ! $coll_card_sharing['selected'] ); ?> hidden>
					<span class="coll-share-switch" aria-hidden="true"></span><span data-coll-share-label><?php echo esc_html( $coll_card_sharing['label'] ); ?></span>
				</button>
			<?php endif; ?>
		</div>
		<?php if ( $coll_card_display['collection'] ) : ?>
			<p class="item-card-collection"><a href="<?php echo esc_url( $coll_card_display['collection_url'] ); ?>"><?php echo esc_html( $coll_card_display['collection'] ); ?></a></p>
		<?php endif; ?>
		<?php if ( $coll_card_display['facts'] ) : ?>
			<p class="item-card-facts"><?php echo esc_html( implode( ' · ', $coll_card_display['facts'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( $coll_card_display['status'] || $coll_card_display['value'] ) : ?>
			<div class="item-card-foot">
				<?php if ( $coll_card_display['status'] ) : ?>
					<span class="pill pill-<?php echo esc_attr( $coll_card_display['status_class'] ); ?>"><?php echo esc_html( $coll_card_display['status'] ); ?></span>
				<?php endif; ?>
				<?php if ( $coll_card_display['value'] ) : ?>
					<span class="item-card-value"><?php echo esc_html( $coll_card_display['value'] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $coll_card_display['sharing'] ) ) : ?>
			<p class="coll-share-message screen-reader-text" role="status" aria-live="polite"></p>
		<?php endif; ?>
		<?php if ( $coll_card_display['fields'] || $coll_card_display['photos'] ) : ?>
			<div class="coll-public-expanded">
				<dl class="coll-public-fields">
					<?php foreach ( $coll_card_display['fields'] as $coll_card_field ) : ?>
						<div><dt><?php echo esc_html( $coll_card_field['label'] ); ?></dt><dd><?php echo esc_html( $coll_card_field['value'] ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
				<?php foreach ( $coll_card_display['photos'] as $coll_card_image ) : ?>
					<figure class="coll-public-photo">
						<a href="<?php echo esc_url( $coll_card_image['url'] ); ?>"><img src="<?php echo esc_url( $coll_card_image['image'] ); ?>" alt="<?php echo esc_attr( $coll_card_display['title'] ); ?>" loading="lazy"></a>
						<?php if ( $coll_card_image['source'] ) : ?>
							<figcaption><a href="<?php echo esc_url( $coll_card_image['source'] ); ?>"><?php echo esc_html__( 'Catalogue image', 'collectibles' ); ?></a></figcaption>
						<?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
