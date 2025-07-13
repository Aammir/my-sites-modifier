<?php
/*
Plugin Name: My Sites Modifier
Description: My Sites in WordPress admin panel with custom layout and additional features.
Network: true
*/
// Enqueue Bootstrap on my-sites.php
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($_SERVER['REQUEST_URI'], 'my-sites.php') !== false) {
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    }
});

// Inject JS and layout logic
add_action('admin_footer', function () {
    if (strpos($_SERVER['REQUEST_URI'], 'my-sites.php') === false) return;

    $user_id = get_current_user_id();
    $sites = get_blogs_of_user($user_id);
    ?>
    <script>
    jQuery(document).ready(function($) {
        //const imgPlaceholder = '<?php echo plugins_url("assets/placeholder.png", __FILE__); ?>';

        const $ul = $('.my-sites').addClass('row g-4').empty(); // clear default list

        <?php foreach ($sites as $site):
            $slug = sanitize_title($site->blogname);
            $img = get_site_option("site-thumbnail-$slug");
            $img_url = esc_url($img ?: plugins_url("assets/placeholder.png", __FILE__));
            $dashboard = get_admin_url($site->userblog_id);
            $site_url = esc_url($site->siteurl);
            $title = esc_js($site->blogname);
        ?>
        $ul.append(`
            <div class="col-md-4" id="card-<?php echo $slug; ?>">
                <div class="card h-100 shadow-sm rounded overflow-hidden">
                    <img src="<?php echo $img_url; ?>" class="card-img-top site-img" id="img-<?php echo $slug; ?>" alt="Site image">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $title; ?></h5>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small>
                                <a href="<?php echo $site_url; ?>" target="_blank">Visit</a> |
                                <a href="<?php echo $dashboard; ?>" target="_blank">Dashboard</a>
                            </small>
                            <form class="upload-form" data-slug="<?php echo $slug; ?>">
                                <input type="file" accept="image/*" name="site_image" class="d-none file-input" />
                                <button type="button" class="btn btn-sm button button-primary select-btn">Change Website Image</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        `);
        <?php endforeach; ?>

        // Trigger file input
        $('.select-btn').on('click', function(e) {
            e.preventDefault();
            $(this).closest('form').find('.file-input').trigger('click');//here
        });

        // Preview + upload
        $('.file-input').on('change', function(e) {
            const form = $(this).closest('form');
            const slug = form.data('slug');
            const input = this;
            const file = input.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                $(`#img-${slug}`).attr('src', e.target.result);//rep inp src
            };
            reader.readAsDataURL(file);

            // Upload via AJAX
            const formData = new FormData();
            formData.append('action', 'upload_site_image');
            formData.append('site_image', file);
            formData.append('slug', slug);
            formData.append('_wpnonce', '<?php echo wp_create_nonce("upload_site_image"); ?>');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(resp) {
                    if (resp.success) {
                        console.log('Image uploaded');
                    } else {
                        alert('Upload failed: ' + resp.data);
                    }
                },
                error: function() {
                    alert('Upload error.');
                }
            });

        });
    });
    </script>
    <?php
});

// Handle AJAX upload
add_action('wp_ajax_upload_site_image', function () {
    if (!current_user_can('manage_network')) {
        wp_send_json_error('Permission denied');
    }

    check_ajax_referer('upload_site_image');

    if (empty($_FILES['site_image']['name']) || empty($_POST['slug'])) {
        wp_send_json_error('Missing file or slug');
    }

    $file = $_FILES['site_image'];
    $slug = sanitize_title($_POST['slug']);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $overrides = ['test_form' => false];
    $upload = wp_handle_upload($file, $overrides);

    if (isset($upload['error'])) {
        wp_send_json_error($upload['error']);
    }

    $url = esc_url_raw($upload['url']);
    update_site_option("site-thumbnail-$slug", $url);
    wp_send_json_success(['url' => $url]);
});

add_action('admin_head', function() {
    if ( strpos($_SERVER['REQUEST_URI'], 'my-sites.php') !== false ) {
        echo '<style>
        body{opacity:0;transition:opacity 0.1s ease-in;background:#f6f7f7;}
        ul.my-sites.row.g-4{overflow:hidden;width:100%;height:100%;padding-bottom:60px;}
        .card.h-100.shadow-sm.rounded{padding:0!important;cursor:pointer;}
        .card-footer{padding:15px;}.error.notice{display:none;}
        </style>';
    }
});

add_action('admin_footer', function() {
    if ( strpos($_SERVER['REQUEST_URI'], 'my-sites.php') !== false ) {
        echo '<script>jQuery(document).ready(function(){ document.body.style.opacity = 1; });</script>';
    }
});
