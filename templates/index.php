<?php
/**
 * Index: every collection of the current user, with the totals across them.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$coll_collections = Collection::get_for_current_user();
$coll_totals      = array(
	'items'  => 0,
	'pieces' => 0,
	'wanted' => 0,
);
$coll_by_currency = array();
$coll_cards       = array();

foreach ( $coll_collections as $coll_collection ) {
	$coll_kind     = Collection::get_kind( $coll_collection->ID );
	$coll_currency = Collection::get_currency( $coll_collection->ID );
	$coll_items    = Item::query( array( 'collection' => $coll_collection->ID ) );
	$coll_summary  = Item::summarize( $coll_items );

	$coll_totals['items']  += $coll_summary['items'];
	$coll_totals['pieces'] += $coll_summary['pieces'];
	$coll_totals['wanted'] += $coll_summary['wanted'];

	if ( ! isset( $coll_by_currency[ $coll_currency ] ) ) {
		$coll_by_currency[ $coll_currency ] = 0.0;
	}

	$coll_by_currency[ $coll_currency ] += $coll_summary['value'];

	$coll_cards[] = array(
		'post'     => $coll_collection,
		'kind'     => $coll_kind,
		'currency' => $coll_currency,
		'summary'  => $coll_summary,
	);
}

$coll_page_title = __( 'Collections', 'collectibles' );
require __DIR__ . '/_head.php';
?>

		<header class="topbar">
			<div>
				<p class="eyebrow"><?php echo esc_html__( 'Collectibles', 'collectibles' ); ?></p>
				<h1><?php echo esc_html__( 'Collections', 'collectibles' ); ?></h1>
			</div>
			<div class="actions">
				<a class="button button-primary" href="<?php echo esc_url( App::get_url( 'collection/new' ) ); ?>">
					<?php echo esc_html__( 'New collection', 'collectibles' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( App::get_url( 'search' ) ); ?>">
					<?php echo esc_html__( 'Search everything', 'collectibles' ); ?>
				</a>
			</div>
		</header>

		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
		<?php if ( isset( $_GET['deleted'] ) ) : ?>
			<div class="notice"><?php echo esc_html__( 'Collection removed.', 'collectibles' ); ?></div>
		<?php endif; ?>

		<?php if ( ! empty( $coll_collections ) ) : ?>
			<section class="stat-strip" aria-label="<?php echo esc_attr__( 'Totals', 'collectibles' ); ?>">
				<div class="stat">
					<span class="stat-value"><?php echo esc_html( number_format_i18n( count( $coll_collections ) ) ); ?></span>
					<span class="stat-label"><?php echo esc_html( _n( 'collection', 'collections', count( $coll_collections ), 'collectibles' ) ); ?></span>
				</div>
				<div class="stat">
					<span class="stat-value"><?php echo esc_html( number_format_i18n( $coll_totals['items'] ) ); ?></span>
					<span class="stat-label"><?php echo esc_html( _n( 'entry', 'entries', $coll_totals['items'], 'collectibles' ) ); ?></span>
				</div>
				<div class="stat">
					<span class="stat-value"><?php echo esc_html( number_format_i18n( $coll_totals['pieces'] ) ); ?></span>
					<span class="stat-label"><?php echo esc_html( _n( 'piece held', 'pieces held', $coll_totals['pieces'], 'collectibles' ) ); ?></span>
				</div>
				<?php foreach ( $coll_by_currency as $coll_currency_code => $coll_currency_total ) : ?>
					<?php if ( $coll_currency_total > 0 ) : ?>
						<div class="stat">
							<span class="stat-value"><?php echo esc_html( Item::format_money( $coll_currency_total, $coll_currency_code ) ); ?></span>
							<span class="stat-label"><?php echo esc_html__( 'estimated value', 'collectibles' ); ?></span>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( $coll_totals['wanted'] > 0 ) : ?>
					<div class="stat">
						<span class="stat-value"><?php echo esc_html( number_format_i18n( $coll_totals['wanted'] ) ); ?></span>
						<span class="stat-label"><?php echo esc_html__( 'on the wishlist', 'collectibles' ); ?></span>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( empty( $coll_collections ) ) : ?>
			<section class="empty-state">
				<p class="empty-icon" aria-hidden="true">🗄️</p>
				<h2><?php echo esc_html__( 'Nothing cataloged yet', 'collectibles' ); ?></h2>
				<p><?php echo esc_html__( 'A collection groups items of one kind, so the app knows which fields to ask you for.', 'collectibles' ); ?></p>
				<p class="kind-hints">
					<?php foreach ( Schema::get_kinds() as $coll_kind_slug => $coll_kind_def ) : ?>
						<span class="kind-hint"><?php echo esc_html( $coll_kind_def['icon'] . ' ' . $coll_kind_def['label'] ); ?></span>
					<?php endforeach; ?>
				</p>
				<a class="button button-primary" href="<?php echo esc_url( App::get_url( 'collection/new' ) ); ?>">
					<?php echo esc_html__( 'Start a collection', 'collectibles' ); ?>
				</a>
			</section>
		<?php else : ?>
			<section class="collection-grid" aria-label="<?php echo esc_attr__( 'Collections', 'collectibles' ); ?>">
				<?php foreach ( $coll_cards as $coll_card ) : ?>
					<?php $coll_card_url = App::get_url( 'collection/' . absint( $coll_card['post']->ID ) ); ?>
					<article class="collection-card">
						<a class="collection-card-icon" href="<?php echo esc_url( $coll_card_url ); ?>" aria-hidden="true" tabindex="-1">
							<?php echo esc_html( Schema::get_kind_icon( $coll_card['kind'] ) ); ?>
						</a>
						<div class="collection-card-body">
							<p class="eyebrow"><?php echo esc_html( Schema::get_kind_label( $coll_card['kind'] ) ); ?></p>
							<h2><a href="<?php echo esc_url( $coll_card_url ); ?>"><?php echo esc_html( get_the_title( $coll_card['post'] ) ); ?></a></h2>
							<p class="collection-card-facts">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: number of entries */
										_n( '%s entry', '%s entries', $coll_card['summary']['items'], 'collectibles' ),
										number_format_i18n( $coll_card['summary']['items'] )
									)
								);
								?>
								<?php if ( $coll_card['summary']['value'] > 0 ) : ?>
									· <?php echo esc_html( Item::format_money( $coll_card['summary']['value'], $coll_card['currency'] ) ); ?>
								<?php endif; ?>
								<?php if ( $coll_card['summary']['wanted'] > 0 ) : ?>
									·
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: number of wishlist entries */
											__( '%s wanted', 'collectibles' ),
											number_format_i18n( $coll_card['summary']['wanted'] )
										)
									);
									?>
								<?php endif; ?>
							</p>
						</div>
					</article>
				<?php endforeach; ?>

				<a class="collection-card collection-card-new" href="<?php echo esc_url( App::get_url( 'collection/new' ) ); ?>">
					<span class="collection-card-icon" aria-hidden="true">＋</span>
					<span class="collection-card-body">
						<span class="collection-card-new-label"><?php echo esc_html__( 'New collection', 'collectibles' ); ?></span>
					</span>
				</a>
			</section>
		<?php endif; ?>

<?php
require __DIR__ . '/_foot.php';
