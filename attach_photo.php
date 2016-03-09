<?php
/*
Plugin Name: Attach Photo
Description: Attach Photo plugin.
Version: 1.0
Author: Web4Pro
*/

add_action('init', 'upload_callback');
/*
 * Upload photos and add data to db.
 */
function upload_callback(){
    if(isset($_POST['submit-photo']) && wp_verify_nonce( $_POST['image_upload_nonce'], 'image_upload' ) && check_if_user_can_upload()){
        $file = $_FILES['image_upload_file'];
        $photo_page_id = get_current_blog_id() == 11 ? get_option('photo_page') : edd_get_option('mch_photos_page');
        if(!$file || ($file['error'] !== 0)){
            wp_redirect(get_permalink($photo_page_id));
            die();
        }
        if(!in_array($file['type'], array('image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'video/mp4', 'video/ogg', 'video/webm'))){
            wp_redirect(get_permalink($photo_page_id));
            die();
        }
        $image_data = getimagesize($file['tmp_name']);
        if(!$image_data && !in_array($file['type'], aph_get_video_mime_types())){
            wp_redirect(get_permalink($photo_page_id));
            die();
        } elseif ( !in_array($image_data[2], array(1,2,3,6)) && !in_array($file['type'], aph_get_video_mime_types()) ){
            wp_redirect(get_permalink($photo_page_id));
            die();
        }
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        
        $content = esc_textarea($_POST['description']);
        $user = wp_get_current_user();
        $new_post = array(
            'post_title'    => get_user_meta($user->ID, 'nickname', true) . ' photo',
            'post_type'     => 'photo',
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_content' => $content,
        );

        if ( get_current_blog_id() == 11 ) {
            if ( current_user_can( 'edit_pages' ) ) {
                $new_post['post_status'] = 'publish';
            } else {
                $new_post['post_status'] = 'draft';
            }
        }

        if($post_id = wp_insert_post($new_post)){
            if($attach_id = media_handle_upload('image_upload_file', $post_id)){
                if ( ! in_array( $file['type'], aph_get_video_mime_types() ) ) {
                    set_post_thumbnail( $post_id, $attach_id );
                }
                
                if ( get_current_blog_id() == 11 ) {
                    if ( function_exists( 'mss_get_user_cluster' ) ) {
                        if ( user_can( $user->ID, 'manage_options' ) ) {
                            update_post_meta($post_id, 'mss_cluster_id', 'all' );
                        } else{
                            update_post_meta($post_id, 'mss_cluster_id', mss_get_user_cluster( $user->ID, true ) );
                        }

                    }
                    if ( $new_post['post_status'] == 'draft' ) {
                        wp_redirect( add_query_arg( array( 'created' => '1' ), get_permalink( get_option( 'photo_page' ) ) ) );
                        die();
                    }
                }

            }
        }
    wp_redirect(get_permalink($photo_page_id));
    die();
    }
}

/*
 * Return true if user can upload photos.
 */
function check_if_user_can_upload(){
    $user = wp_get_current_user();

    if ( get_current_blog_id() === 11 ) {
        if ( is_user_logged_in() && ( in_array( 'ss_users', $user->roles ) || in_array('administrator', $user->roles) ) ) {
            return true;
        } else {
            return false;
        }
    } else {
        $trial_period = get_usermeta($user->ID, 'hub_ending_trial_time', true);
        $trial_off = false;
        if($trial_period){
            if(time() > $trial_period){
                $trial_off = true;
            }
        }else{
            $trial_off = true;
        }
        if(is_user_logged_in() && ((in_array('author', $user->roles) || in_array('administrator', $user->roles) || (mch_user_has_access($user->ID, 'community') && $trial_off)))){
            return true;
        } else {
            return false;
        }
    }
}

add_shortcode('show_photos', 'show_photos');
/*
 * Display photos and file input(the last one is only for special users).
 */
function show_photos($atts){
    ob_start();
    if(check_if_user_can_upload()) : ?>
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
                <?php wp_nonce_field( 'image_upload', 'image_upload_nonce' ); ?>
                <div class="button-wraper">
                    <input name="submit-photo" type="submit" value="<?php _e('Upload', 'mch'); ?>" />
                </div>
            </div>
        </form>
        <?php if ( $_GET['created'] == 1 ) {
            echo __( 'The post has created and sent for moderation!' );
        }
        ?>
        <div id="photo-content"></div>
        <div id="inifiniteLoader">
            <div class="img-loading">
                <?php if (get_current_blog_id() === 5) {
                    $image = 'img/loading.gif';
                } else {
                    $image = 'img/loading-red.gif';
                } ?>
                <img src="<?php echo plugin_dir_url(__FILE__) . $image; ?>" />
            </div>
        </div>
    <?php else: ?>
        <div class="error-msg"><h2><?php _e('Please login to view Photos.', 'mch'); ?></h2></div>
    <?php endif; ?>  
    <?php
    return ob_get_clean();
}

/*
 * Ajax callback function
 */
function wp_infinitepaginate(){ 
    if(!check_if_user_can_upload()){
        die();
    }
    if(isset($_POST['page_no']) && is_numeric($_POST['page_no'])){
        $paged = (int)$_POST['page_no'];
    } else {
        $paged = 1;
    }
    if(isset($_POST['post_count']) && is_numeric($_POST['post_count'])){
        $post_count = (int)$_POST['post_count'];
    } else {
        $post_count = 10;
    }
    $args = array(
        'post_type' => 'photo',
        'posts_per_page' => $post_count,
        'paged' => $paged,
        'post_status' => 'publish'
    );

    if ( get_current_blog_id() == 11 ) {
        if ( function_exists( 'mss_get_user_cluster' ) ) {
            $args['meta_query'] = array(
                    array(
                            'key'     => 'mss_cluster_id',
                            'value'   => array('all', mss_get_user_cluster( get_current_user_id(), true ) ),
                            'compare' => 'IN',
                    ),
            );
        }
    }

    $posts_query = new WP_Query( $args );
    ob_start();
    ?>
    <?php if ( $posts_query->have_posts() ) : ?>
        <?php if($paged == 1): ?><ul id="list-photo" class="digital-products-list"><?php endif; ?>
        <?php if($paged !== 1){
            $item = ($paged - 1) * 10 + 1; 
        }else {
            $item = 1;
        } ?>
        <?php while ( $posts_query->have_posts() ) : $posts_query->the_post(); ?>
            <li data-item="<?php echo $item++; ?>">
                <a href="<?php the_permalink();?>">
                    <?php if (has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail('thumbnail');?>
                    <?php else : ?>
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'img/video-thumbnail.png'; ?>" />
                    <?php endif; ?>
                </a>
            </li>
        <?php  endwhile; ?>
        <?php if($paged == 1): ?></ul><?php endif; ?>
        <?php wp_reset_query(); ?>
    <?php endif;
    $output = ob_get_clean();
    if($output){
        echo $output;
    }
    die();
}

add_action('wp_ajax_infinite_scroll', 'wp_infinitepaginate');
add_action('wp_ajax_nopriv_infinite_scroll', 'wp_infinitepaginate');

add_action('wp_enqueue_scripts', 'add_scripts');

function add_scripts(){
    if(is_page(edd_get_option('mch_photos_page')) || get_current_blog_id() === 11 ){
        $args = array(
            'post_type' => 'photo',
            'posts_per_page' => 10,
        );
        $posts_query = new WP_Query( $args );
        wp_enqueue_style('ajax_scroll_style',  plugin_dir_url(__FILE__) . 'css/style.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery_cookie', plugin_dir_url(__FILE__) . 'js/jquery.cookie.js');
        wp_enqueue_script( 'ajax_scroll', plugin_dir_url(__FILE__). 'js/ajax_scroll.js');
        wp_localize_script( 'ajax_scroll', 'data',  
                array('ajax_url' => admin_url('admin-ajax.php'), 
                    'max_pages' => $posts_query->max_num_pages,
                    'page' => 3
                )
        );
    }
    
}

/*
 * Display images in photo post type in admin menu.
 */
add_filter('manage_edit-photo_columns', 'add_image_column');
function add_image_column( $columns ){
    unset($columns['date']);
    $add_column['image'] = __('Image', 'mch');
    $result = array_merge($columns, $add_column);
    $result['date'] = __('Date', 'mch');
    return $result;
}

add_filter('manage_photo_posts_custom_column', 'fill_image_column', 5, 2);
function fill_image_column($column_name, $post_id) {
    if( $column_name != 'image' ){
        return;
    }
    echo get_the_post_thumbnail($post_id, array(50, 50));
}

/**
 * Return available mime types of video.
 * 
 * @return type
 */
function aph_get_video_mime_types() {
    return apply_filters( 'aph_get_video_mime_types', array( 'video/mp4', 'video/ogg', 'video/webm' ) );
}
