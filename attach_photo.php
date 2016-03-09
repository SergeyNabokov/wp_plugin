<?php
/*
  Plugin Name: Media Section
  Description: Media Section plugin.
  Version: 1.0
 */

add_action('init', 'ms_upload_callback');
/**
 * Upload photos and add data to db.
 */
function ms_upload_callback() {
    if (isset($_POST['submit-photo']) && wp_verify_nonce($_POST['image_upload_nonce'], 'image_upload') && ms_check_if_user_can_upload()) {
        $file = $_FILES['image_upload_file'];
        $photo_page_link = get_permalink(edd_get_option('mch_photos_page'));
        if (!$file || ($file['error'] !== 0)) {
            wp_redirect($photo_page_link);
            die();
        }
        if (!in_array($file['type'], array('image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'video/mp4', 'video/ogg', 'video/webm'))) {
            wp_redirect($photo_page_link);
            die();
        }
        $image_data = getimagesize($file['tmp_name']);
        if (!$image_data && !in_array($file['type'], ms_get_video_mime_types())) {
            wp_redirect($photo_page_link);
            die();
        } elseif ( !in_array($image_data[2], array(1,2,3,6)) && !in_array($file['type'], ms_get_video_mime_types()) ) {
            wp_redirect($photo_page_link);
            die();
        }
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $content = esc_textarea($_POST['description']);
        $user = wp_get_current_user();
        $new_post = array(
            'post_title' => get_user_meta($user->ID, 'nickname', true) . ' photo',
            'post_type' => 'photo',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'post_content' => $content,
            'post_status' => 'publish',
        );

        if ($post_id = wp_insert_post($new_post)) {
            if ($attach_id = media_handle_upload('image_upload_file', $post_id)) {
                if (!in_array($file['type'], ms_get_video_mime_types())) {
                    set_post_thumbnail($post_id, $attach_id);
                }
            }
        }
        wp_redirect($photo_page_link);
        die();
    }
}

/**
 * Return true if user can upload photos.
 */
function ms_check_if_user_can_upload() {
    $user = wp_get_current_user();
    $trial_period = get_usermeta($user->ID, 'hub_ending_trial_time', true);
    $trial_off = false;
    if ($trial_period) {
        if (time() > $trial_period) {
            $trial_off = true;
        }
    } else {
        $trial_off = true;
    }
    if (is_user_logged_in() && ((in_array('author', $user->roles) || in_array('administrator', $user->roles) || $trial_off))) {
        return true;
    } else {
        return false;
    }
}

add_shortcode('show_photos', 'ms_show_photos');
/**
 * Display photos and file input(the last one is only for special users).
 */
function ms_show_photos($atts) {
    ob_start();
    if (ms_check_if_user_can_upload()) :
        ?>
        <form method="post" class="upload_photo_form" enctype="multipart/form-data">
            <div class="input-wraper">
                <label for="image_upload_file"><?php _e('Add new photo or video', 'mch'); ?></label>
            </div>
            <div class="input-wraper">
                <div class="e-btn btn-file">
                    <div class="button-name"></div>
                    <input type="file" name="image_upload_file" />
                </div>
                <div class="filename"></div>
            </div>
            <div class="input-wrap-textarea">
                <textarea name="description" placeholder="<?php _e('Photo description', 'mch'); ?>"></textarea>
                <?php wp_nonce_field('image_upload', 'image_upload_nonce'); ?>
                <div class="button-wraper">
                    <input name="submit-photo" type="submit" value="<?php _e('Upload', 'mch'); ?>" />
                </div>
            </div>
        </form>
        <?php
        if ($_GET['created'] == 1) {
            _e('The post has created and sent for moderation!');
        }
        ?>
        <div id="photo-content"></div>
        <div id="inifiniteLoader">
            <div class="img-loading">
                <img src="<?php echo plugin_dir_url(__FILE__) . 'img/loading.gif'; ?>" />
            </div>
        </div>
    <?php else: ?>
        <div class="error-msg"><h2><?php _e('Please login to view Photos.', 'mch'); ?></h2></div>
    <?php endif; ?>  
    <?php
    return ob_get_clean();
}


add_action('wp_ajax_infinite_scroll', 'ms_infinitepaginate');
add_action('wp_ajax_nopriv_infinite_scroll', 'ms_infinitepaginate');
/**
 * Ajax callback function
 */
function ms_infinitepaginate() {
    if (!ms_check_if_user_can_upload()) {
        die();
    }
    if (isset($_POST['page_no']) && is_numeric($_POST['page_no'])) {
        $paged = (int) $_POST['page_no'];
    } else {
        $paged = 1;
    }
    if (isset($_POST['post_count']) && is_numeric($_POST['post_count'])) {
        $post_count = (int) $_POST['post_count'];
    } else {
        $post_count = 10;
    }
    $args = array(
        'post_type' => 'photo',
        'posts_per_page' => $post_count,
        'paged' => $paged,
        'post_status' => 'publish'
    );

    $posts_query = new WP_Query($args);
    ob_start();
    ?>
        <?php if ($posts_query->have_posts()) : ?>
            <?php if ($paged == 1): ?><ul id="list-photo" class="digital-products-list"><?php endif; ?>
        <?php
        if ($paged !== 1) {
            $item = ($paged - 1) * 10 + 1;
        } else {
            $item = 1;
        }
        ?>
        <?php while ($posts_query->have_posts()) : $posts_query->the_post(); ?>
                <li data-item="<?php echo $item++; ?>">
                    <a href="<?php the_permalink(); ?>">
                <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('thumbnail'); ?>
            <?php else : ?>
                            <img src="<?php echo plugin_dir_url(__FILE__) . 'img/video-thumbnail.png'; ?>" />
            <?php endif; ?>
                    </a>
                </li>
        <?php endwhile; ?>
        <?php if ($paged == 1): ?></ul><?php endif; ?>
        <?php wp_reset_query(); ?>
    <?php
    endif;
    $output = ob_get_clean();
    if ($output) {
        echo $output;
    }
    die();
}

add_action('wp_enqueue_scripts', 'ms_add_scripts');
/**
 * Add scripts and styles.
 */
function ms_add_scripts() {
    if (is_page(edd_get_option('mch_photos_page'))) {
        $args = array(
            'post_type' => 'photo',
            'posts_per_page' => 10,
        );
        $posts_query = new WP_Query($args);
        wp_enqueue_style('ajax_scroll_style', plugin_dir_url(__FILE__) . 'css/style.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery_cookie', plugin_dir_url(__FILE__) . 'js/jquery.cookie.js');
        wp_enqueue_script('ajax_scroll', plugin_dir_url(__FILE__) . 'js/ajax_scroll.js');
        wp_localize_script('ajax_scroll', 'data', array('ajax_url' => admin_url('admin-ajax.php'),
            'max_pages' => $posts_query->max_num_pages,
            'page' => 3
                )
        );
    }
}

add_filter('manage_edit-photo_columns', 'ms_add_image_column');
/**
 * Add image column in photo post type in admin menu.
 */
function ms_add_image_column($columns) {
    unset($columns['date']);
    $add_column['image'] = __('Image', 'mch');
    $result = array_merge($columns, $add_column);
    $result['date'] = __('Date', 'mch');
    return $result;
}

add_filter('manage_photo_posts_custom_column', 'ms_fill_image_column', 5, 2);
/**
 * Display images in photo post type in admin menu.
 */
function ms_fill_image_column($column_name, $post_id) {
    if ($column_name != 'image') {
        return;
    }
    echo get_the_post_thumbnail($post_id, array(50, 50));
}

/**
 * Return available mime types of video.
 */
function ms_get_video_mime_types() {
    return apply_filters('ms_get_video_mime_types', array('video/mp4', 'video/ogg', 'video/webm'));
}
