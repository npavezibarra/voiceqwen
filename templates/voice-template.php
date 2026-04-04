<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?> style="padding: 0; margin: 0; overflow-x: hidden;">

<div id="primary" class="content-area" style="padding: 0; margin: 0; border: none; min-height: 100vh;">
    <main id="main" class="site-main" style="padding: 0; margin: 0; border: none;">
        <?php
        while ( have_posts() ) :
            the_post();
            the_content();
        endwhile;
        ?>
    </main>
</div>

<?php wp_footer(); ?>
</body>
</html>
