<?php
/**
 * Where the collection comes from: a world map and a list by continent.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$coll_items   = Item::query( array( 'orderby' => 'recent' ) );
$coll_origins = Item::summarize_origins( $coll_items );

$coll_unplaced = $coll_origins[''] ?? array(
	'items'  => 0,
	'pieces' => 0,
);
unset( $coll_origins[''] );

// The map shades present-day territories, so a historic issuer counts towards
// the territory it sat in.
$coll_territories = array();

foreach ( $coll_origins as $coll_code => $coll_counts ) {
	$coll_territory = Geography::get_map_territory( $coll_code );

	if ( '' === $coll_territory ) {
		continue;
	}

	$coll_territories[ $coll_territory ] = ( $coll_territories[ $coll_territory ] ?? 0 ) + $coll_counts['items'];
}

$coll_most = $coll_origins ? max( wp_list_pluck( $coll_origins, 'items' ) ) : 0;
$coll_peak = $coll_territories ? max( $coll_territories ) : 0;

// Group the rows by continent, keeping each continent's rows in count order.
$coll_by_continent = array();

foreach ( $coll_origins as $coll_code => $coll_counts ) {
	$coll_continent = Geography::get_continent( $coll_code );

	if ( '' === $coll_continent ) {
		continue;
	}

	$coll_by_continent[ $coll_continent ][ $coll_code ] = $coll_counts;
}

$coll_continent_names = Geography::get_continent_names();
$coll_total_items     = count( $coll_items );

$coll_page_title  = __( 'Origins', 'collectibles' );
$coll_shell_class = 'shell';

require __DIR__ . '/_head.php';
?>

		<header class="topbar">
			<div>
				<a class="crumb" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Collections', 'collectibles' ); ?></a>
				<h1><?php echo esc_html( $coll_page_title ); ?></h1>
				<p class="eyebrow"><?php echo esc_html__( 'Where in the world the collection comes from.', 'collectibles' ); ?></p>
			</div>
		</header>

		<?php if ( empty( $coll_origins ) && 0 === $coll_unplaced['items'] ) : ?>
			<section class="empty-state">
				<h2><?php echo esc_html__( 'Nothing to place yet', 'collectibles' ); ?></h2>
				<p><?php echo esc_html__( 'Once items record a country, they show up here.', 'collectibles' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( App::get_url() ); ?>">
					<?php echo esc_html__( 'Back to collections', 'collectibles' ); ?>
				</a>
			</section>
		<?php else : ?>
			<div class="stat-strip">
				<div class="stat">
					<span class="stat-value"><?php echo esc_html( number_format_i18n( count( $coll_origins ) ) ); ?></span>
					<span class="stat-label"><?php echo esc_html__( 'Places', 'collectibles' ); ?></span>
				</div>
				<div class="stat">
					<span class="stat-value"><?php echo esc_html( number_format_i18n( count( $coll_by_continent ) ) ); ?></span>
					<span class="stat-label"><?php echo esc_html__( 'Continents', 'collectibles' ); ?></span>
				</div>
				<div class="stat">
					<span class="stat-value"><?php echo esc_html( number_format_i18n( $coll_total_items ) ); ?></span>
					<span class="stat-label"><?php echo esc_html__( 'Items', 'collectibles' ); ?></span>
				</div>
			</div>

			<?php $coll_map = Geography::get_map_svg(); ?>
			<?php if ( '' !== $coll_map && ! empty( $coll_territories ) ) : ?>
				<section class="panel origins-map-panel">
					<style>
						<?php
						// One rule per territory: five steps of ink, the darkest
						// for wherever the most of the collection comes from.
						foreach ( $coll_territories as $coll_territory => $coll_count ) {
							$coll_step = $coll_peak > 0 ? (int) ceil( 5 * $coll_count / $coll_peak ) : 1;
							$coll_step = min( 5, max( 1, $coll_step ) );

							printf(
								'.origins-map path[data-code="%s"]{fill:var(--coll-map-%d);cursor:pointer}',
								esc_attr( $coll_territory ),
								absint( $coll_step )
							);
						}
						?>
					</style>

					<div class="origins-map">
						<?php
						// The file ships with the plugin: a static SVG of paths.
						echo wp_kses(
							$coll_map,
							array(
								'svg'   => array(
									'xmlns'      => true,
									'viewbox'    => true,
									'class'      => true,
									'role'       => true,
									'aria-label' => true,
								),
								'g'     => array( 'class' => true ),
								'path'  => array(
									'd'         => true,
									'data-code' => true,
								),
								'title' => array(),
							)
						);
						?>
					</div>

					<p class="field-hint"><?php echo esc_html__( 'Shaded by how much of the collection comes from there. Pick a country to see what came from it.', 'collectibles' ); ?></p>

					<?php
					// Clicking the map is a shortcut for the list below it,
					// which stays the keyboard-reachable way to the same place.
					// A territory shaded only by a historic issuer leads to that
					// issuer rather than to a country with nothing in it.
					$coll_map_links = array();

					foreach ( $coll_origins as $coll_code => $coll_counts ) {
						$coll_territory = Geography::get_map_territory( $coll_code );

						if ( '' === $coll_territory ) {
							continue;
						}

						$coll_is_country = Geography::to_stored_code( $coll_territory ) === $coll_code;

						if ( $coll_is_country || ! isset( $coll_map_links[ $coll_territory ] ) ) {
							$coll_map_links[ $coll_territory ] = add_query_arg( 'origin', $coll_code, App::get_url( 'search' ) );
						}
					}
					?>
					<script>
						( function () {
							var links = <?php echo wp_json_encode( $coll_map_links ); ?>;
							var map = document.querySelector( '.origins-map' );

							if ( ! map ) {
								return;
							}

							map.addEventListener( 'click', function ( event ) {
								var path = event.target.closest( 'path[data-code]' );
								var href = path && links[ path.getAttribute( 'data-code' ) ];

								if ( href ) {
									window.location.href = href;
								}
							} );
						}() );
					</script>
				</section>
			<?php endif; ?>

			<?php foreach ( $coll_continent_names as $coll_continent => $coll_continent_name ) : ?>
				<?php if ( empty( $coll_by_continent[ $coll_continent ] ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>

				<section class="panel">
					<h2>
						<?php echo esc_html( $coll_continent_name ); ?>
						<span class="origin-count">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html( _n( '%s item', '%s items', array_sum( wp_list_pluck( $coll_by_continent[ $coll_continent ], 'items' ) ), 'collectibles' ) ),
								esc_html( number_format_i18n( array_sum( wp_list_pluck( $coll_by_continent[ $coll_continent ], 'items' ) ) ) )
							);
							?>
						</span>
					</h2>

					<ul class="origin-list">
						<?php foreach ( $coll_by_continent[ $coll_continent ] as $coll_code => $coll_counts ) : ?>
							<li class="origin-row">
								<a href="<?php echo esc_url( add_query_arg( 'origin', $coll_code, App::get_url( 'search' ) ) ); ?>">
									<span class="origin-flag" aria-hidden="true"><?php echo esc_html( Geography::get_flag( $coll_code ) ); ?></span>
									<span class="origin-name"><?php echo esc_html( Geography::get_name( $coll_code ) ); ?></span>
									<span class="origin-bar" aria-hidden="true">
										<span class="origin-bar-fill" style="width: <?php echo esc_attr( $coll_most > 0 ? round( 100 * $coll_counts['items'] / $coll_most, 1 ) : 0 ); ?>%"></span>
									</span>
									<span class="origin-number"><?php echo esc_html( number_format_i18n( $coll_counts['items'] ) ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>

			<?php if ( $coll_unplaced['items'] > 0 ) : ?>
				<section class="panel">
					<h2><?php echo esc_html__( 'Not placed yet', 'collectibles' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: number of items */
							esc_html( _n( '%s item has no country recorded.', '%s items have no country recorded.', $coll_unplaced['items'], 'collectibles' ) ),
							esc_html( number_format_i18n( $coll_unplaced['items'] ) )
						);
						?>
					</p>
					<p><a class="button" href="<?php echo esc_url( add_query_arg( 'origin', 'none', App::get_url( 'search' ) ) ); ?>"><?php echo esc_html__( 'Show them', 'collectibles' ); ?></a></p>
				</section>
			<?php endif; ?>
		<?php endif; ?>

<?php
require __DIR__ . '/_foot.php';
