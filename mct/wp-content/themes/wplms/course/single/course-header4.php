<?php
/**
 * The template for displaying Course Header
 *
 * Override this template by copying it to yourtheme/course/single/header.php
 *
 * @author 		VibeThemes
 * @package 	vibe-course-module/templates
 * @version     2.0
 */

do_action( 'bp_before_course_header' );

?>
<div class="row">
	<div class="col-md-12">
		<div id="item-header-content">
			<?php vibe_breadcrumbs(); ?>
			<h1><a href="<?php bp_course_permalink(); ?>" title="<?php bp_course_name(); ?>"><?php bp_course_name(); ?></a></h1>
			
			<?php do_action( 'bp_before_course_header_meta' ); ?>

			<div id="item-meta">
				<?php bp_course_meta(); ?>
				<?php do_action( 'bp_course_header_actions' ); ?>

				<?php do_action( 'bp_course_header_meta' ); ?>

			</div>
			<div class="course_excerpt">
			<?php the_excerpt(); ?>
			</div>
		</div><!-- #item-header-content -->
	</div>
</div>
<div class="row">
	<div class="col-md-9">
		<div id="item-header-avatar">
				<?php bp_course_avatar(); ?>
		</div><!-- #item-header-avatar -->
	</div>
	<div class="col-md-3">
		<div class="widget pricing">
			<?php the_course_button(); ?>
			<?php the_course_details(); ?>
		</div>
		<div id="item-admins">
			<h3><?php _e( 'Instructors', 'vibe' ); ?></h3>
			<?php
				bp_course_instructor();
				do_action( 'bp_after_course_menu_instructors' );
			?>
		</div><!-- #item-actions -->
	</div>
</div>	
<?php
do_action( 'bp_after_course_header' );
?>