<?php
/**
 * Shared document head and page shell opening.
 *
 * Expects $coll_page_title and optionally $coll_shell_class to be set by the
 * including template.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$coll_page_title  = isset( $coll_page_title ) ? $coll_page_title : __( 'Collectibles', 'collectibles' );
$coll_shell_class = isset( $coll_shell_class ) ? $coll_shell_class : 'shell';
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $coll_page_title ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="<?php echo esc_attr( $coll_shell_class ); ?>">
