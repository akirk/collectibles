<?php
/**
 * Public collection directory; receives only public summaries.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$coll_entries = Sharing::get_directory();
wp_enqueue_style( 'collectibles-public', App::get_asset_url( 'collectibles.css' ), array(), (string) filemtime( dirname( __DIR__ ) . '/assets/collectibles.css' ) );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html__( 'Shared collections', 'collectibles' ); ?></title>
	<?php wp_styles()->do_items( array( 'collectibles-public' ) ); ?>
</head>
<body class="coll-public">
<main class="shell">
	<header class="topbar">
		<h1><?php echo esc_html__( 'Shared collections', 'collectibles' ); ?></h1>
	</header>
	<?php if ( ! $coll_entries ) : ?>
		<p class="empty-state"><?php echo esc_html__( 'No collections are shared yet.', 'collectibles' ); ?></p>
	<?php else : ?>
		<section class="collection-grid" aria-label="<?php echo esc_attr__( 'Shared collections', 'collectibles' ); ?>">
			<?php foreach ( $coll_entries as $coll_entry ) : ?>
				<article class="collection-card">
					<span class="collection-card-icon" aria-hidden="true"><?php echo esc_html( $coll_entry['icon'] ); ?></span>
					<div class="collection-card-body">
						<p class="eyebrow"><?php echo esc_html( $coll_entry['owner'] ); ?></p>
						<h2><a href="<?php echo esc_url( $coll_entry['url'] ); ?>"><?php echo esc_html( $coll_entry['title'] ); ?></a></h2>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
</main>
</body>
</html>
