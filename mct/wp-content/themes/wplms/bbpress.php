<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header(vibe_get_header());

if ( have_posts() ) : while ( have_posts() ) : the_post();

?>
<section id="title">
    <div class="<?php echo vibe_get_container(); ?>">
        <div class="row">
            <div class="col-md-9 col-sm-8">
                <?php bbp_breadcrumb(); ?>
                <div class="pagetitle">
                    <h1><?php the_title(); ?></h1>
                    <?php 
                    if(bbp_is_forum_archive()){
                        _e('All Forums directory','vibe');
                    }
                    if(bbp_is_single_forum()){
                        bbp_forum_subscription_link();
                        bbp_single_forum_description();
                    }

                    if(bbp_is_single_topic()){
                        bbp_topic_tag_list(); 
                        bbp_single_topic_description();
                    }

                    ?>
                    
                </div>
            </div>
            <div class="col-md-3 col-sm-4">
                <?php if ( bbp_allow_search() ) : ?>

                    <div class="bbp-search-form">

                        <?php bbp_get_template_part( 'form', 'search' ); ?>

                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<section id="content">
    <div class="<?php echo vibe_get_container(); ?>">
        <div class="row">
            <div class="col-md-9">

                <div class="content">
                    <?php
                        the_content();
                     ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bbpress_sidebar">
                    <?php
                        $sidebar = apply_filters('wplms_sidebar','bbpress');
                        if ( !function_exists('dynamic_sidebar')|| !dynamic_sidebar($sidebar) ) : ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
endwhile;
endif;
?>
<?php
get_footer( vibe_get_footer() ); 