<?php
/**
 * Plugin Name: KTS Export as JSON
 * Description: Exports ClassicPress site content as JSON
 * Version: 1.0
 * Author: Tim Kaye
 * Author URI: https://timkaye.org
 * Requires CP: 2.5
 * Requires PHP: 8.0
 * Requires at least: 6.2.3
 * License: GPLv3
 * Text Domain: kts-export-json
 */

/**
 * Adds the plugin as a submenu item within the Tools menu.
 */
function kts_json_export_menu() {
	add_submenu_page( 'tools.php', 'Export as JSON', 'Export as JSON', 'manage_options', 'export-as-json', 'kts_json_export_page' );
}
add_action( 'admin_menu', 'kts_json_export_menu' );

/**
 * Enqueues the relevant JS and CSS files.
 */
function kts_json_export_enqueue( $hook ) {
	if ( $hook !== 'tools_page_export-as-json' ) {
		return;
	}
	wp_enqueue_style( 'kts-json-export', plugins_url( '/css/export.css' , __FILE__ ) );
	wp_enqueue_script( 'kts-json-export', plugins_url( '/js/export.js' , __FILE__ ) );
}
add_action( 'admin_enqueue_scripts', 'kts_json_export_enqueue' ); 

/**
 * Builds the HTML for the plugin page.
 */
function kts_json_export_page() {

	// Get all post categories.
	$categories = get_categories();

	// Get all users.
	$users = get_users();

	// Get all authors of posts.
	$post_authors = get_users(
		array(
			'has_published_posts' => array( 'post' ),
		)
	);

	// Get months when at least one post was published.
	$after_string = '';
	$before_string = '';
	$archives_string = wp_get_archives(
		array(
			'type'   => 'monthly',
			'format' => 'option',
			'echo'   => false,
		)
	);
	$months = explode( PHP_EOL, $archives_string );
	if ( $months ) {
		foreach ( $months as $month ) {
			if ( $month !== '' ) {
				$options = explode( '>', $month );
				$url_parts = explode( '/', $options[0] );
				$year_month = $url_parts[4] . '-' . $url_parts[5];
				$after_string .= str_replace( $options[0], '<option value="' . $year_month . '-01T00:00:00"', $month );
				$before_string .= str_replace( $options[0], '<option value="' . date( 'Y-m-t', strtotime( $year_month ) ) . 'T23:59:59"', $month );
			}
		}
	}

	// Get post statuses.
	$post_statuses = get_post_stati();

	// Get all authors of pages.
	$page_authors = get_users(
		array(
			'has_published_posts' => array( 'page' ),
		)
	);

	// Get media categories.
	$media_cats = get_terms(
		array(
			'taxonomy'   => 'media_category',
			'hide_empty' => false,
		)
	);

	// Get all custom post types registered with REST API.
	$custom_post_types = get_post_types(
		array(
			'_builtin'     => false,
			'show_in_rest' => true,
		),
		$output   = 'objects',
		$operator = 'and'
	);

	$per_page_option = ! empty( get_option( 'kts-export-json-per-page' ) ) ? absint( get_option( 'kts-export-json-per-page' ) ) : 50;
	ob_start();
	?>

	<div class="wrap">
		<h1><?php esc_html_e( 'Export as JSON', 'kts-export-json' ); ?></h1>

		<p><?php esc_html_e( 'When you click the Export button below, ClassicPress will create a JSON file for you to save to your computer.', 'kts-export-json' ); ?></p>
		<p><?php esc_html_e( 'This file will contain your posts, pages, media, users, or whatever other option you have chosen.', 'kts-export-json' ); ?></p>
		<p><?php esc_html_e( 'Once you’ve saved the download file, you can import the JSON into another ClassicPress installation, or just keep the file as a backup.', 'kts-export-json' ); ?></p>

		<details>
			<summary>
				<h2><?php esc_html_e( 'Batch size', 'kts-export-json' ); ?></h2>
			</summary>

			<fieldset id="panel-settings" data-message="<?php esc_attr_e( 'Batch size updated!', 'kts-export-json' ); ?>">
				<legend>
					<p><?php esc_html_e( 'Records are retrieved from the database in batches. By default, the size of a batch is set at 50. On shared hosting, you might need to reduce this to 10 or 20 in order to avoid the export timing out.', 'kts-export-json' ); ?></p>
					<p><?php esc_html_e( 'On faster hosts, you might be able to speed up the generation of the export file by increasing this number to 100.', 'kts-export-json' ); ?></p>
					<p><em><?php esc_html_e( 'Increasing this number above 100 is not recommended and will require custom code.', 'kts-export-json' ); ?></em></p>
				</legend>
				<div class="options">
					<input id="per-page-10" type="radio" name="per_page" value="10" <?php checked( 10, $per_page_option ); ?>>
					<label for="per-page-10">10</label>
				</div>
				<div class="options">
					<input id="per-page-20" type="radio" name="per_page" value="20" <?php checked( 20, $per_page_option ); ?>>
					<label for="per-page-20">20</label>
				</div>
				<div class="options">
					<input id="per-page-50" type="radio" name="per_page" value="50" <?php checked( 50, $per_page_option ); ?>>
					<label for="per-page-50">50</label>
				</div>
				<div class="options">
					<input id="per-page-100" type="radio" name="per_page" value="100" <?php checked( 100, $per_page_option ); ?>>
					<label for="per-page-100">100</label>
				</div>
			</fieldset>
		</details>

		<h2><?php esc_html_e( 'Choose what to export', 'kts-export-json' ); ?></h2>

		<div id="export-tabs">

			<div id="export-tabs-nav" role="tablist">
				<button id="tab-posts" class="export-tab active" aria-controls="panel-posts" aria-selected="true" role="tab"><?php esc_html_e( 'Posts', 'kts-export-json' ); ?></button>
				<button id="tab-pages" class="export-tab" aria-controls="panel-pages" aria-selected="false" role="tab"><?php esc_html_e( 'Pages', 'kts-export-json' ); ?></button>
				<button id="tab-categories" class="export-tab" aria-controls="panel-categories" aria-selected="false" role="tab"><?php esc_html_e( 'Categories', 'kts-export-json' ); ?></button>
				<button id="tab-tags" class="export-tab" aria-controls="panel-tags" aria-selected="false" role="tab"><?php esc_html_e( 'Tags', 'kts-export-json' ); ?></button>
				<button id="tab-taxonomies" class="export-tab" aria-controls="panel-taxonomies" aria-selected="false" role="tab"><?php esc_html_e( 'Taxonomies', 'kts-export-json' ); ?></button>
				<button id="tab-comments" class="export-tab" aria-controls="panel-comments" aria-selected="false" role="tab"><?php esc_html_e( 'Comments', 'kts-export-json' ); ?></button>
				<button id="tab-menus" class="export-tab" aria-controls="panel-menus" aria-selected="false" role="tab"><?php esc_html_e( 'Nav Menus', 'kts-export-json' ); ?></button>
				<button id="tab-media" class="export-tab" aria-controls="panel-media" aria-selected="false" role="tab"><?php esc_html_e( 'Media', 'kts-export-json' ); ?></button>
				<button id="tab-users" class="export-tab" aria-controls="panel-users" aria-selected="false" role="tab"><?php esc_html_e( 'Users', 'kts-export-json' ); ?></button>

				<?php
				if ( $custom_post_types ) {
					foreach ( $custom_post_types as $custom_post_type ) {
						?>

						<button id="tab-<?php esc_attr_e( $custom_post_type->rest_base ); ?>" class="export-tab" aria-controls="panel-<?php esc_attr_e( $custom_post_type->rest_base ); ?>" aria-selected="false" role="tab"><?php esc_html_e( $custom_post_type->labels->name, 'kts-export-json' ); ?></button>

						<?php
					}
				}
				?>

			</div>

			<form id="export-form" action="" method="get" data-message="<?php esc_attr_e( 'The end date is earlier than the start date. Please reset these dates.', 'kts-export-json' ); ?>">
				<div id="export-panels">

					<fieldset id="panel-posts" class="export-panel" aria-hidden="false" role="tabpanel" aria-labelledby="posts">
						<ul id="post-filters" class="export-filters non-checkbox-panel">

							<?php // Dropdown for categories ?>
							<li>
								<label for="categories"><span class="label-responsive"><?php esc_html_e( 'Category: ', 'kts-export-json' ); ?></span></label>

								<?php
								wp_dropdown_categories(
									array(
										'name'            => 'categories',
										'show_option_all' => esc_html( 'All', 'kts-export-json' ),
									)
								);
								?>

							</li>
							<li>
								<label for="tags"><span class="label-responsive"><?php esc_html_e( 'Tags: ', 'kts-export-json' ); ?></span></label>
								<textarea id="tags" name="post_tags" aria-describedby="post-tags-legend" placeholder="<?php esc_attr_e( 'All', 'kts-export-json' ); ?>"></textarea>
								<fieldset class="post-ids-fieldset">
									<legend id="post-tags-legend"><?php esc_html_e( 'List specific tags, separated by a comma, or go with the default of "All".', 'kts-export-json' ); ?></legend>
								</fieldset>
							</li>
							<li>
								<label for="post-ids"><span class="label-responsive"><?php esc_html_e( 'IDs: ', 'kts-export-json' ); ?></span></label>
								<textarea id="post-ids" name="include" aria-describedby="post-ids-legend" placeholder="<?php esc_attr_e( 'All', 'kts-export-json' ); ?>"></textarea>
								<fieldset id="post-ids-fieldset" class="post-ids-fieldset" disabled>
									<legend id="post-ids-legend"><?php esc_html_e( 'List specific IDs, separated by a comma, and choose whether to include or exclude them from the export.', 'kts-export-json' ); ?></legend>
									<label for="posts-include"><?php esc_html_e( 'Include ', 'kts-export-json' ); ?></label>
									<input id="posts-include" type="radio" name="include-exclude" value="include" checked>
									<br>
									<label for="posts-exclude"><?php esc_html_e( 'Exclude ', 'kts-export-json' ); ?></label>
									<input id="posts-exclude" type="radio" name="include-exclude" value="exclude">
								</fieldset>
							</li>

							<?php // Dropdown for authors ?>
							<li>
								<label for="post-author"><span class="label-responsive"><?php esc_html_e( 'Author: ', 'kts-export-json' ); ?></span></label>
								<select id="post-author" name="author" class="">
									<option value="0" selected><?php esc_html_e( 'All', 'kts-export-json' ); ?></option>

									<?php
									foreach ( $post_authors as $post_author ) {
										?>

										<option value="<?php echo absint( $post_author->ID ); ?>"><?php esc_html_e( $post_author->display_name ); ?> (<?php esc_html_e( $post_author->user_login ); ?>)</option>

										<?php
									}
									?>

								</select>
							</li>

							<li>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Date range: ', 'kts-export-json' ); ?></legend>
									<label for="post-start-date" class="label-responsive"><?php esc_html_e( 'Start date: ', 'kts-export-json' ); ?></label>
									<select id="post-start-date" name="start_date">
										<option value="0"><?php esc_html_e( 'Select', 'kts-export-json' ); ?></option>
										<?php echo $after_string; ?>
									</select> 

									<label for="post-end-date" class="label-responsive"><?php esc_html_e( 'End date: ', 'kts-export-json' ); ?></label>
									<select id="post-end-date" name="end_date">
										<option value="0"><?php esc_html_e( 'Select', 'kts-export-json' ); ?></option>
										<?php echo $before_string; ?>
									</select>
								</fieldset>
							</li>

							<li>
								<label for="post-status" class="label-responsive"><?php esc_html_e( 'Status: ', 'kts-export-json' ); ?></label>
								<select id="post-status" name="status">
									<option value="" selected><?php esc_html_e( 'Any' ); ?></option>

									<?php
									foreach ( $post_statuses as $key => $post_status ) {
										?>

										<option value="<?php esc_attr_e( $key ); ?>"><?php esc_html_e( ucfirst( $post_status ) ); ?></option>

										<?php
									}
									?>

								</select>
							</li>
						</ul>
					</fieldset>

					<fieldset id="panel-pages" class="export-panel hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-pages" disabled inert>
						<ul id="page-filters" class="export-filters non-checkbox-panel">
							
							<li>
								<label for="page_id"><span class="label-responsive"><?php esc_html_e( 'Page: ', 'kts-export-json' ); ?></span></label>

								<?php
								wp_dropdown_pages(
									array(
										'name'             => 'include',
										'show_option_none' => esc_html( 'All', 'kts-export-json' ),
									)
								);
								?>

							</li>

							<?php // Dropdown for authors ?>
							<li>
								<label for="page-author"><span class="label-responsive"><?php esc_html_e( 'Author: ', 'kts-export-json' ); ?></span></label>
								<select id="page-author" name="author" class="" disabled>
									<option value="0" selected><?php esc_html_e( 'All', 'kts-export-json' ); ?></option>

									<?php
									foreach ( $page_authors as $page_author ) {
										?>

										<option value="<?php absint( $page_author->ID ); ?>"><?php esc_html( $page_author->display_name ); ?> (<?php esc_html( $page_author->user_login ); ?>)</option>

										<?php 
									}
									?>

								</select>
							</li>

							<li>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Date range: ' ); ?></legend>
									<label for="page-start-date" class="label-responsive"><?php esc_html_e( 'Start date: ', 'kts-export-json' ); ?></label>
									<select id="page-start-date" name="start_date" disabled>
										<option value="0"><?php esc_html_e( 'Select', 'kts-export-json' ); ?></option>
										<?php echo $after_string; ?>
									</select> 

									<label for="page-end-date" class="label-responsive"><?php esc_html_e( 'End date: ', 'kts-export-json' ); ?></label>
									<select id="page-end-date" name="end_date" disabled>
										<option value="0"><?php esc_html_e( 'Select', 'kts-export-json' ); ?></option>
										<?php echo $before_string; ?>
									</select>
								</fieldset>
							</li>

							<?php // Page Statuses ?>
							<li>
								<label for="page-status" class="label-responsive"><?php esc_html_e( 'Status: ', 'kts-export-json' ); ?></label>
								<select id="page-status" name="status" disabled>
									<option value="publish" selected><?php esc_html_e( 'Published' ); ?></option>

									<?php
									foreach ( $post_statuses as $key => $post_status ) {
										?>

										<option value="<?php esc_attr_e( $key ); ?>"><?php esc_html_e( $post_status ); ?></option>

										<?php
									}
									?>

								</select>
							</li>
						</ul>
					</fieldset>

					<fieldset id="panel-categories" class="export-panel hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-categories" disabled inert>
						<legend><b><?php esc_html_e( 'Select the categories whose details you wish to export.', 'kts-export-json' ); ?></b></legend>
						<div class="options">
							<input id="categories-all" type="checkbox" name="categories[]" value="0" required disabled>
							<label for="categories-all"><?php esc_html_e( 'ALL', 'kts-export-json' ); ?></label>
						</div>

						<?php
						foreach ( $categories as $category ) {
							?>

							<div class="options">
								<input id="categories-<?php echo absint( $category->term_id ); ?>"type="checkbox" name="categories[]" value="<?php echo absint( $category->term_id ); ?>" required disabled>
								<label for="categories-<?php echo absint( $category->term_id ); ?>"><?php esc_html_e( $category->name ); ?></label>
							</div>

							<?php
						}
						?>

					</fieldset>

					<fieldset id="panel-tags" class="export-panel columns hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-tags" disabled inert>
						<legend><b><?php esc_html_e( 'Select the tags whose details you wish to export.', 'kts-export-json' ); ?></b></legend>
						<div class="options">
							<input id="tags-all" type="checkbox" name="tags[]" value="0" required disabled>
							<label for="tags-all"><?php esc_html_e( 'ALL', 'kts-export-json' ); ?></label>
						</div>

						<?php
						foreach ( get_tags() as $tag ) {
							?>

							<div class="options">
								<input id="tags-<?php echo absint( $tag->term_id ); ?>" type="checkbox" name="tags[]" value="<?php echo absint( $tag->term_id ); ?>" required disabled>
								<label for="tags-<?php echo absint( $tag->term_id ); ?>"><?php esc_html_e( $tag->name ); ?></label>
							</div>

							<?php
						}
						?>

					</fieldset>

					<fieldset id="panel-taxonomies" class="export-panel columns hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-taxonomies" disabled inert>
						<legend><b><?php esc_html_e( 'Select the taxonomies whose terms you wish to export.', 'kts-export-json' ); ?></b></legend>
						<div class="options">
							<input id="taxonomies-all" type="checkbox" name="taxonomies[]" value="0" required disabled>
							<label for="taxonomies-all"><?php esc_html_e( 'ALL', 'kts-export-json' ); ?></label>
						</div>

						<?php
						foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
							?>

							<div class="options">
								<input id="<?php esc_attr_e( 'taxonomies-' . $taxonomy->name ); ?>" type="checkbox" name="taxonomies[]" value="<?php esc_attr_e( $taxonomy->name ); ?>" required disabled>
								<label for="<?php esc_attr_e( 'taxonomies-' . $taxonomy->name ); ?>"><?php esc_html_e( $taxonomy->labels->singular_name ); ?></label>
							</div>

							<?php
						}
						?>

					</fieldset>

					<fieldset id="panel-comments" class="export-panel hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-comments" disabled inert>
						<legend><b><?php esc_html_e( 'Select the post types whose comments you wish to export.', 'kts-export-json' ); ?></b></legend>

						<?php
						$comments_supported = false;
						$post_types = $custom_post_types;
						$post_types[] = get_post_type_object( 'post' );
						foreach ( $post_types as $post_type ) {
							if ( post_type_supports( $post_type->name, 'comments' ) ) {
								$comments_supported = true;
								break;
							}
						}
						if ( $comments_supported === true ) {
							?>

							<div class="options">
								<input id="post-types-all" type="checkbox" name="post_types[]" value="0" required disabled>
								<label for="post-types-all"><?php esc_html_e( 'ALL', 'kts-export-json' ); ?></label>
							</div>

							<?php
						}

						if ( post_type_supports( 'post', 'comments' ) ) {
							?>

							<div class="options">
								<input id="post-types-post" type="checkbox" name="post_types[]" value="post" required disabled>
								<label for="post-types-post"><?php esc_html_e( 'Post', 'kts-export-json' ); ?></label>
							</div>

							<?php
						}

						if ( $custom_post_types ) {
							foreach ( $custom_post_types as $custom_post_type ) {
								if ( post_type_supports( $custom_post_type->name, 'comments' ) ) {
									?>

									<div class="options">
										<input id="<?php esc_attr_e( 'post-types-' . $custom_post_type->name ); ?>" type="checkbox" name="post_types[]" value="<?php esc_attr_e( $custom_post_type->name ); ?>" required disabled>
										<label for="<?php esc_attr_e( 'post-types-' . $custom_post_type->name ); ?>"><?php esc_html_e( $custom_post_type->labels->name ); ?></label>
									</div>

									<?php
								}
							}
						}

						if ( $comments_supported === false ) {
							?>

							<p><em><?php esc_html_e( 'None of the post types currently registered on this site support comments.', 'kts-export-json' ); ?></em></p>

							<?php
						}
						?>

					</fieldset>

					<fieldset id="panel-menus" class="export-panel hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-menus" disabled inert>
						<legend><b><?php esc_html_e( "Select the active theme's navigation menus whose items you wish to export.", 'kts-export-json' ); ?></b></legend>
						<div class="options">
							<input id="menus-all" type="checkbox" name="menus[]" value="0" required disabled>
							<label for="menus-all"><?php esc_html_e( 'ALL', 'kts-export-json' ); ?></label>
						</div>

						<?php
						foreach ( get_registered_nav_menus() as $location => $menu_name ) {
							$nav_menu_id = 0;
							foreach ( get_nav_menu_locations() as $menu_location => $menu_id ) {
								if ( $location === $menu_location ) {
									$nav_menu_id = $menu_id;
								}
							}
							?>

							<div class="options">
								<input id="menus-<?php echo absint( $nav_menu_id ); ?>" type="checkbox" name="menus[]" value="<?php echo absint( $nav_menu_id ); ?>" required disabled>
								<label for="menus-<?php echo absint( $nav_menu_id ); ?>"><?php esc_html_e( $menu_name ); ?></label>
							</div>

							<?php
						}
						?>

					</fieldset>

					<fieldset id="panel-media" class="export-panel hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-media" disabled inert>
						<ul id="attachment-filters" class="export-filters non-checkbox-panel">

							<li>
								<fieldset>
									<legend><b><?php esc_html_e( 'Media Type: ', 'kts-export-json' ); ?></b></legend>
									<div class="media-types" style="margin-left: 105px; margin-top: -1.5em;font-weight: normal;">
										<input id="media-type-all" type="radio" name="media_type" value="" checked>
										<label for="media-type-all"><?php esc_html_e( 'ALL', 'kts-export-json' ); ?></label>
										<br>
										<input id="media-type-application" type="radio" name="media_type" value="application">
										<label for="media-type-application"><?php esc_html_e( 'Application', 'kts-export-json' ); ?></label>
										<br>
										<input id="media-type-audio" type="radio" name="media_type" value="audio">
										<label for="media-type-audio"><?php esc_html_e( 'Audio', 'kts-export-json' ); ?></label>
										<br>
										<input id="media-type-image" type="radio" name="media_type" value="image">
										<label for="media-type-image"><?php esc_html_e( 'Image', 'kts-export-json' ); ?></label>
										<br>
										<input id="media-type-text" type="radio" name="media_type" value="text>">
										<label for="media-type-text"><?php esc_html_e( 'Text', 'kts-export-json' ); ?></label>
										<br>
										<input id="media-type-video" type="radio" name="media_type" value="video">
										<label for="media-type-video"><?php esc_html_e( 'Video', 'kts-export-json' ); ?></label>
									</div>
								</fieldset>
							</li>

							<?php // Dropdown for media categories ?>
							<li>
								<label for="media-category"><span class="label-responsive"><?php esc_html_e( 'Media Category: ', 'kts-export-json' ); ?></span></label>
								<select id="media-category" name="media_categories" disabled>
									<option value="0" selected><?php esc_html_e( 'All', 'kts-export-json' ); ?></option>

									<?php
									foreach ( $media_cats as $media_cat ) {
										?>
										<option class="level-0" value="<?php echo absint( $media_cat->term_id ); ?>"><?php esc_html_e( $media_cat->name ); ?></option>
										<?php
									}
									?>

								</select>
							</li>

							<li>
								<label for="media-tags"><span class="label-responsive"><?php esc_html_e( 'Media Tags: ', 'kts-export-json' ); ?></span></label>
								<textarea id="media-tags" name="media_tags" aria-describedby="media-tags-legend" placeholder="<?php esc_attr_e( 'All', 'kts-export-json' ); ?>"></textarea>
								<fieldset class="post-ids-fieldset">
									<legend id="media-tags-legend"><?php esc_html_e( 'List specific tags, separated by a comma, or go with the default of "All".', 'kts-export-json' ); ?></legend>
								</fieldset>
							</li>

							<li>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Date range: ', 'kts-export-json' ); ?></legend>
									<label for="attachment-start-date" class="label-responsive"><?php esc_html_e( 'Start date: ', 'kts-export-json' ); ?></label>
									<select id="attachment-start-date" name="start_date" disabled>
										<option value="0"><?php esc_html_e( 'Select', 'kts-export-json' ); ?></option>
										<?php echo $after_string; ?>
									</select> 

									<label for="attachment-end-date" class="label-responsive"><?php esc_html_e( 'End date: ', 'kts-export-json' ); ?></label>
									<select id="attachment-end-date" name="end_date" disabled>
										<option value="0"><?php esc_html_e( 'Select', 'kts-export-json' ); ?></option>
										<?php echo $before_string; ?>
									</select>
								</fieldset>
							</li>
						</ul>
					</fieldset>

					<fieldset id="panel-users" class="export-panel hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-users" disabled inert>
						<ul id="user-filters" class="export-filters non-checkbox-panel">
							<li>
								<div class="options">
									<label for="users-published" class="users-options"><?php esc_html_e( 'Export only those users who have published a post', 'kts-export-json' ); ?></label>
									<input id="users-published" type="checkbox" name="has_published_posts" value="">
								</div>
							</li>

							<?php // Dropdown for roles ?>
							<li class="users-dropdown">
								<label for="users-roles" class="users-options"><span class="label-responsive"><?php esc_html_e( 'Export only those with the following role:', 'kts-export-json' ); ?></span></label>
								<select id="users-roles" name="roles">
									<option value="0" selected><?php esc_html_e( 'All', 'kts-export-json' ); ?></option>

									<?php wp_dropdown_roles(); ?>

								</select>
							</li>
							<li>
								<fieldset class="columns">
									<legend><b><?php esc_html_e( 'Or choose specific users from the list below:', 'kts-export-json' ); ?></b></legend>
									<div class="options">
										<input id="users-all" type="checkbox" name="users[]" value="0">
										<label for="users-all"><?php esc_html_e( 'ALL', 'kts-export-json' ); ?></label>
									</div>

									<?php
									foreach ( $users as $user ) {
										?>

										<div class="options">
											<input id="users-<?php echo absint( $user->ID ); ?>" type="checkbox" name="users[]" value="<?php echo absint( $user->ID ); ?>" disabled>
											<label for="users-<?php echo absint( $user->ID ); ?>"><?php esc_html_e( $user->display_name ); ?> (<?php esc_html_e( $user->user_login ); ?>)</label>
										</div>

										<?php
									}
									?>

								</fieldset>
							</li>						
						</ul>
					</fieldset>

					<?php
					if ( $custom_post_types ) {
						foreach ( $custom_post_types as $custom_post_type ) {
							?>

							<fieldset id="panel-<?php esc_attr_e( $custom_post_type->rest_base ); ?>" class="export-panel hidden" aria-hidden="true" role="tabpanel" aria-labelledby="tab-<?php esc_attr_e( $custom_post_type->rest_base ); ?>" disabled inert>

								<ul id="<?php esc_attr_e( $custom_post_type->name ); ?>-filters" class="export-filters non-checkbox-panel">
		
									<li>
										<label for="<?php esc_attr_e( $custom_post_type->name . '-ids' ); ?>"><span class="label-responsive"><?php esc_html_e( 'IDs: ', 'kts-export-json' ); ?></span></label>
										<textarea id="<?php esc_attr_e( $custom_post_type->name . '-ids' ); ?>" name="include" aria-describedby="<?php esc_attr_e( $custom_post_type->name . '-legend' ); ?>" placeholder="<?php esc_attr_e( 'All', 'kts-export-json' ); ?>"></textarea>
										<fieldset id="<?php esc_attr_e( $custom_post_type->name . '-ids-fieldset' ); ?>" class="post-ids-fieldset" disabled>
											<legend id="<?php esc_attr_e( $custom_post_type->name . '-legend' ); ?>"><?php esc_html_e( 'List specific IDs, separated by a comma, and choose whether to include or exclude them from the export.', 'kts-export-json' ); ?></legend>
											<label for="<?php esc_attr_e( $custom_post_type->name . '-include' ); ?>"><?php esc_html_e( 'Include ', 'kts-export-json' ); ?></label>
											<input id="<?php esc_attr_e( $custom_post_type->name . '-include' ); ?>" type="radio" name="<?php esc_attr_e( $custom_post_type->name ); ?>-include-exclude" value="include" checked>
											<br>
											<label for="<?php esc_attr_e( $custom_post_type->name . '-exclude' ); ?>"><?php esc_html_e( 'Exclude ', 'kts-export-json' ); ?></label>
											<input id="<?php esc_attr_e( $custom_post_type->name . '-exclude' ); ?>" type="radio" name="<?php esc_attr_e( $custom_post_type->name ); ?>-include-exclude" value="exclude">
										</fieldset>
									</li>

							<?php // Dropdown for authors ?>
							<li>
								<label for="<?php esc_attr_e( $custom_post_type->name . '-author' ); ?>"><span class="label-responsive"><?php esc_html_e( 'Author: ', 'kts-export-json' ); ?></span></label>
								<select id="<?php esc_attr_e( $custom_post_type->name . '-author' ); ?>" name="author" class="">
									<option value="0" selected><?php esc_html_e( 'All', 'kts-export-json' ); ?></option>

									<?php
									$custom_post_authors = get_users(
										array(
											'has_published_posts' => array( $custom_post_type->name ),
										)
									);
									foreach ( $custom_post_authors as $custom_post_author ) {
										?>

										<option value="<?php echo absint( $custom_post_author->ID ); ?>"><?php esc_html_e( $custom_post_author->display_name ); ?> (<?php esc_html_e( $custom_post_author->user_login ); ?>)</option>

										<?php
									}
									?>

								</select>
							</li>

							<li>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Date range: ', 'kts-export-json' ); ?></legend>
									<label for="<?php esc_attr_e( $custom_post_type->name . '-start-date' ); ?>" class="label-responsive"><?php esc_html_e( 'Start date: ', 'kts-export-json' ); ?></label>
									<select id="<?php esc_attr_e( $custom_post_type->name . '-start-date' ); ?>" name="start_date">
										<option value="0"><?php esc_html_e( 'Select', 'kts-export-json' ); ?></option>
										<?php echo $after_string; ?>
									</select> 

									<label for="<?php esc_attr_e( $custom_post_type->name . '-end-date' ); ?>" class="label-responsive"><?php esc_html_e( 'End date: ', 'kts-export-json' ); ?></label>
									<select id="<?php esc_attr_e( $custom_post_type->name . '-end-date' ); ?>" name="end_date">
										<option value="0"><?php esc_html_e( 'Select', 'kts-export-json' ); ?></option>
										<?php echo $before_string; ?>
									</select>
								</fieldset>
							</li>

							<li>
								<label for="<?php esc_attr_e( $custom_post_type->name . '-status' ); ?>" class="label-responsive"><?php esc_html_e( 'Status: ', 'kts-export-json' ); ?></label>
								<select id="<?php esc_attr_e( $custom_post_type->name . '-status' ); ?>" name="status">
									<option value="" selected><?php esc_html_e( 'Any' ); ?></option>

									<?php
									foreach ( $post_statuses as $key => $post_status ) {
										?>

										<option value="<?php esc_attr_e( $key ); ?>"><?php esc_html_e( ucfirst( $post_status ) ); ?></option>

										<?php
									}
									?>

								</select>
							</li>
								</ul>
							</fieldset>

							<?php
						}
					}
					?>

				</div>

				<?php // Button for exporting ?>
				<button type="submit" class="button-primary"><?php esc_html_e( 'Download Export File', 'kts-export-json' ); ?></button>
				<input type="hidden" name="download" value="true">
				<input type="hidden" name="type" value="posts">
				<input type="hidden" name="per_page" value="<?php echo $per_page_option; ?>">

			</form>
		</div>
	</div>

	<?php
	echo ob_get_clean();
}

/**
 * Exports the content to a JSON file.
 *
 * Method uses the REST API internally.
 *
 * Based on https://wpscholar.com/blog/internal-wp-rest-api-calls/
 */
function kts_export_to_json() {

	// Check the current user's capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Don't run unless it's for a download
	if ( empty( $_GET['download'] ) ) {
		return;
	}

	// Get all custom post types registered with REST API.
	$custom_post_types = get_post_types(
		array(
			'public'       => true,
			'_builtin'     => false,
			'show_in_rest' => true,
		),
		$output   = 'objects',
		$operator = 'and'
	);

	$rest_bases = array();
	foreach ( $custom_post_types as $custom_post_type ) {
		$rest_bases[] = $custom_post_type->rest_base;
	}

	// Don't run without a specific endpoint
	$type = $_GET['type'];
	if ( empty( $type ) || ( ! in_array( $type, $rest_bases, true ) && ! in_array( $type,
		array(
			'posts', 'pages', 'categories', 'tags', 'taxonomies', 'comments', 'menus', 'media', 'users', 
		),
		true
	) ) ) {
		return;
	}

	// Check we're on the right admin page
	global $pagenow;
	if ( $pagenow !== 'tools.php' ) {
		return;
	}

	// Set the $request for the appropriate endpoint
	$request = new WP_REST_Request( 'GET', '/wp/v2/' . $type );

	// Set export filename
	$filename = get_bloginfo( 'name' ) . '-json-' . $type . '-' . date( 'Y-m-d\TH-i-s' ) . '.json';
 
	// Select appropriate endpoint
	if ( in_array( $type, $rest_bases, true ) || in_array( $type,
		array(
			'posts',
			'pages',
			'media',
		),
		true
	) ) {

		// Create the appropriate tags array
		$tag_ids = array();
		$get_tags = ( $type === 'media' ) ? $_GET['media_tags'] : $_GET['post_tags'];
		$tag_type = ( $type === 'media' ) ? 'media_post_tag' : 'post_tag';
		$tags = array_map( 'sanitize_title', array_map( 'trim', explode( ',', $get_tags ) ) );
		$tags = array_filter( $tags );
		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tag_ids[] = get_term_by( 'slug', $tag, $tag_type )->term_id;
			}
		}

		// Compile the args
		$args = array(
			'author'      => isset( $_GET['author'] ) ? absint( $_GET['author'] ) : 0,
			'categories'  => isset( $_GET['categories'] ) ? absint( $_GET['categories'] ) : '',
			'tags'        => isset( $_GET['post_tags'] ) && ! empty( $tag_ids ) ? $tag_ids : '',
			'include'     => isset( $_GET['include'] ) ? array_map( 'absint', array_map( 'trim', explode( ',', $_GET['include'] ) ) ) : '',
			'exclude'     => isset( $_GET['exclude'] ) ? array_map( 'absint', array_map( 'trim', explode( ',', $_GET['exclude'] ) ) ) : '',	
			'after'       => isset( $_GET['start_date'] ) ? $_GET['start_date'] : '',
			'before'      => isset( $_GET['end_date'] ) ? $_GET['end_date'] : '',	
			'status'      => $type === 'media' ? 'inherit' : ( isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '' ),
			'media_type'  => $type === 'media' && isset( $_GET['media_type'] ) ? sanitize_text_field( $_GET['media_type'] ) : '',
		);

		if ( $type === 'media' ) {
			$args['media_categories'] = isset( $_GET['media_categories'] ) ? absint( $_GET['media_categories'] ) : '';
			$args['media_tags'] = ! empty( $tag_ids ) ? $tag_ids : '';
		}
	} else {
		$items = array();
		$includes = 0;

		if ( $type === 'comments' && isset( $_GET['post_types'] ) ) {
			$items = array_map( 'sanitize_text_field', array_map( 'trim', $_GET['post_types'] ) );
			if ( in_array( '0', $items, true ) ) {
				$includes = 0;
			} else {
				$total_ids = array();
				foreach ( $items as $post_type ) {
					$post_ids = get_posts(
						array(
							'post_type'     => $post_type,
							'comment_count' => array(
								'value'   => 0,
								'compare' => '>',
							),
							'numberposts'   => -1,
							'fields'        => 'ids',
						)
					);
					$total_ids = array_merge( $total_ids, $post_ids );
				}

				$includes = get_comments(
					array(
						'fields'      => 'ids',
						'post__in'    => $total_ids,
						'numberposts' => -1,
					)
				);
			}
		} else {
			if ( $type === 'menus' && isset( $_GET['menus'] ) ) {
				$items = array_map( 'sanitize_text_field', array_map( 'trim', $_GET['menus'] ) );
				$includes = ( ! in_array( 'all', $items, true ) ) ? $items : 0;
			} else {
				if ( $type === 'categories' && isset( $_GET['categories'] ) ) {
					$items = array_map( 'absint', array_map( 'trim', $_GET['categories'] ) );
				} elseif ( $type === 'tags' && isset( $_GET['tags'] ) ){
					$items = array_map( 'absint', array_map( 'trim', $_GET['tags'] ) );
				} elseif ( $type === 'taxonomies' && isset( $_GET['taxonomies'] ) ) {
					$items = array_map( 'absint', array_map( 'trim', $_GET['taxonomies'] ) );
				} elseif ( $type === 'users' && isset( $_GET['users'] ) ) {
					$items = array_map( 'absint', array_map( 'trim', $_GET['users'] ) );
				}
				$includes = ( ! in_array( 0, $items, true ) ) ? $items : 0;
			}
		}

		// Compile the args
		$args = array();
		$args['include'] = $includes;

		if ( $type === 'users' && empty( $includes ) ) { // Apply only if no specific users selected
			$args['roles'] = isset( $_GET['roles'] ) ? sanitize_text_field( $_GET['roles'] ) : '';
			$args['has_published_posts'] = isset( $_GET['has_published_posts'] ) ? true : false;
		}
	}

	// Remove empty args
	foreach ( $args as $key => $arg ) {
		if ( is_array( $arg ) && empty( $arg[0] ) ) {
			unset( $args[$key] );
		} elseif ( empty( $arg ) ) {
			unset( $args[$key] );
		}
	}

	// Add universal args
	$args['page'] = 1;

	$per_page = absint( $_POST['per_page'] );
	if ( empty( $per_page ) || ! in_array( $per_page, array( 10, 20, 50, 100 ), true ) ) {
		$per_page = ! empty( get_option( 'kts-export-json-per-page' ) ) ? absint( get_option( 'kts-export-json-per-page' ) ) : 50;
	}
	$args['per_page'] = $per_page;

	// Create empty array as temporary storage for retrieved records
	$total_records = array();

	// Create loop to make queries to REST API in batches
	do {
		// Make the request to the endpoint
		$request->set_query_params( $args );
		$response = rest_do_request( $request );
		$server = rest_get_server();

		// Embed authors and featured images in response
		$data = $server->response_to_data( $response, true );

		if ( empty( $data ) ) {
			break;
		}
		$total_records = array_merge( $total_records, $data );
		$args['page']++;

	// Continue while a full batch of records is returned
	} while ( count( $data ) === $args['per_page'] );

	// Filter media results by media category and media post tags if appropriate
	if ( $type === 'media' ) {
		foreach ( $total_records as $key => $record ) {
			if ( ! empty( $args['media_categories'] ) ) {
				if ( empty( $record['media_categories'] ) || ! in_array( $args['media_categories'], $record['media_categories'], true ) ) {
					unset( $total_records[$key] );
				}
			}
			if ( ! empty( $args['media_tags'] ) ) {
				if ( empty( $record['media_tags'] ) ) {
					unset( $total_records[$key] );
				} else {
					if ( empty( array_intersect( $record['media_tags'], $args['media_tags'] ) ) ) {
						unset( $total_records[$key] );
					}
				}
			}
		}
	}

	// Encode merged records
	$json = wp_json_encode( $total_records );

	// Create the downloadable file
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Content-Type: application/json' );
	echo $json;
	exit;
}
add_action( 'admin_init', 'kts_export_to_json');

/*
 * Add new admin-ajax endpoint to update per_page option
 */
function kts_export_json_per_page() {
	$per_page = absint( $_POST['per_page'] );
	if ( $per_page === 0 ) {
		wp_send_json_error( __( 'Error: The value supplied was not an integer.', 'kts-export-json' ), 400 );
	} else {
		update_option( 'kts-export-json-per-page', $per_page );
		wp_send_json_success();
	}
}
add_action( 'wp_ajax_export_json_per_page', 'kts_export_json_per_page' );
