<?php
/**
 * ============================================================================
 * TEMPLATE: Admin Room Form
 * ============================================================================
 * 
 * Form thêm/sửa loại phòng khách sạn.
 * 
 * Variables available:
 * @var object|null $room        Room object (null nếu thêm mới)
 * @var array       $surcharges  Danh sách surcharges của phòng
 * @var array       $hotels      Danh sách hotels
 * @var int         $hotel_id    Pre-selected hotel ID
 * @var int         $room_id     Room ID (0 nếu thêm mới)
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin/Templates
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

$is_edit = !empty($room);
$page_title = $is_edit ? sprintf(__('Sửa: %s', 'flavor'), $room->name) : __('Thêm Loại Phòng', 'flavor');

// Parse JSON fields
$amenities   = $is_edit && !empty($room->amenities) ? json_decode($room->amenities, true) : array();
$gallery_ids = $is_edit && !empty($room->gallery_ids) ? json_decode($room->gallery_ids, true) : array();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-rooms')); ?>" class="page-title-action">
        <?php esc_html_e('← Quay lại', 'flavor'); ?>
    </a>
    <hr class="wp-header-end">

    <form id="vie-room-form" method="post">
        <input type="hidden" name="room_id" value="<?php echo esc_attr($room_id); ?>">
        <?php wp_nonce_field('vie_hotel_rooms_nonce', 'nonce'); ?>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">

                <!-- ============================================================
                     MAIN CONTENT
                ============================================================ -->
                <div id="post-body-content">

                    <!-- Thông tin cơ bản -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php esc_html_e('Thông tin cơ bản', 'flavor'); ?></h2></div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th><label for="hotel_id"><?php esc_html_e('Khách sạn', 'flavor'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <select name="hotel_id" id="hotel_id" class="regular-text" required>
                                            <option value=""><?php esc_html_e('-- Chọn khách sạn --', 'flavor'); ?></option>
                                            <?php foreach ($hotels as $hotel) : ?>
                                                <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($hotel_id, $hotel->ID); ?>>
                                                    <?php echo esc_html($hotel->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="name"><?php esc_html_e('Tên loại phòng', 'flavor'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <input type="text" name="name" id="name" class="regular-text" required
                                               value="<?php echo esc_attr($room->name ?? ''); ?>"
                                               placeholder="VD: Deluxe Double Room">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="description"><?php esc_html_e('Mô tả', 'flavor'); ?></label></th>
                                    <td>
                                        <textarea name="description" id="description" class="large-text" rows="4"
                                                  placeholder="<?php esc_attr_e('Mô tả ngắn về loại phòng...', 'flavor'); ?>"><?php echo esc_textarea($room->description ?? ''); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Sức chứa -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php esc_html_e('Sức chứa', 'flavor'); ?></h2></div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th><label for="base_occupancy"><?php esc_html_e('Số người tiêu chuẩn', 'flavor'); ?></label></th>
                                    <td>
                                        <input type="number" name="base_occupancy" id="base_occupancy" 
                                               value="<?php echo esc_attr($room->base_occupancy ?? 2); ?>" min="1" max="10" style="width:80px">
                                        <span class="description"><?php esc_html_e('người/phòng', 'flavor'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="max_occupancy"><?php esc_html_e('Sức chứa tối đa', 'flavor'); ?></label></th>
                                    <td>
                                        <input type="number" name="max_occupancy" id="max_occupancy" 
                                               value="<?php echo esc_attr($room->max_occupancy ?? 4); ?>" min="1" max="20" style="width:80px">
                                        <span class="description"><?php esc_html_e('người/phòng (bao gồm trẻ em)', 'flavor'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="max_adults"><?php esc_html_e('Người lớn tối đa', 'flavor'); ?></label></th>
                                    <td>
                                        <input type="number" name="max_adults" id="max_adults" 
                                               value="<?php echo esc_attr($room->max_adults ?? 2); ?>" min="1" max="10" style="width:80px">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="max_children"><?php esc_html_e('Trẻ em tối đa', 'flavor'); ?></label></th>
                                    <td>
                                        <input type="number" name="max_children" id="max_children" 
                                               value="<?php echo esc_attr($room->max_children ?? 2); ?>" min="0" max="10" style="width:80px">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="total_rooms"><?php esc_html_e('Số lượng phòng', 'flavor'); ?></label></th>
                                    <td>
                                        <input type="number" name="total_rooms" id="total_rooms" 
                                               value="<?php echo esc_attr($room->total_rooms ?? 1); ?>" min="1" max="1000" style="width:80px">
                                        <span class="description"><?php esc_html_e('phòng có sẵn', 'flavor'); ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Thông tin phòng -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php esc_html_e('Thông tin phòng', 'flavor'); ?></h2></div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th><label for="room_size"><?php esc_html_e('Diện tích (m²)', 'flavor'); ?></label></th>
                                    <td>
                                        <input type="number" name="room_size" id="room_size" 
                                               value="<?php echo esc_attr($room->room_size ?? ''); ?>" min="0" step="0.1" style="width:100px">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="bed_type"><?php esc_html_e('Loại giường', 'flavor'); ?></label></th>
                                    <td>
                                        <input type="text" name="bed_type" id="bed_type" class="regular-text"
                                               value="<?php echo esc_attr($room->bed_type ?? ''); ?>"
                                               placeholder="VD: 1 King Bed / 2 Single Beds">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="view_type"><?php esc_html_e('View', 'flavor'); ?></label></th>
                                    <td>
                                        <input type="text" name="view_type" id="view_type" class="regular-text"
                                               value="<?php echo esc_attr($room->view_type ?? ''); ?>"
                                               placeholder="VD: Sea View, Garden View">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Phụ thu (Surcharges) -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php esc_html_e('Phụ thu', 'flavor'); ?></h2></div>
                        <div class="inside">
                            <p class="description" style="margin-bottom:15px;">
                                <?php esc_html_e('Thiết lập phụ thu: Extra bed, trẻ em theo tuổi, người lớn thêm...', 'flavor'); ?>
                            </p>
                            
                            <div id="vie-surcharges-container">
                                <?php if (!empty($surcharges)) : ?>
                                    <?php foreach ($surcharges as $i => $s) : ?>
                                    <div class="vie-repeater-item" data-index="<?php echo $i; ?>" style="border:1px solid #dcdcde;padding:12px;margin-bottom:10px;border-radius:4px;background:#f9f9f9;">
                                        <div class="vie-repeater-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                            <strong><?php esc_html_e('Phụ thu', 'flavor'); ?> #<span class="row-number"><?php echo $i + 1; ?></span></strong>
                                            <button type="button" class="vie-remove-surcharge button-link" style="color:#b32d2e;">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </button>
                                        </div>
                                        <div class="vie-repeater-body" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:10px;">
                                            <div class="field">
                                                <label style="display:block;font-weight:500;margin-bottom:4px;"><?php esc_html_e('Loại', 'flavor'); ?></label>
                                                <select name="surcharges[<?php echo $i; ?>][surcharge_type]" class="surcharge-type-select" style="width:100%;">
                                                    <option value="child" <?php selected($s->surcharge_type ?? '', 'child'); ?>><?php esc_html_e('Trẻ em', 'flavor'); ?></option>
                                                    <option value="adult" <?php selected($s->surcharge_type ?? '', 'adult'); ?>><?php esc_html_e('NL thêm', 'flavor'); ?></option>
                                                    <option value="extra_bed" <?php selected($s->surcharge_type ?? '', 'extra_bed'); ?>><?php esc_html_e('Giường phụ', 'flavor'); ?></option>
                                                    <option value="breakfast" <?php selected($s->surcharge_type ?? '', 'breakfast'); ?>><?php esc_html_e('Bữa sáng', 'flavor'); ?></option>
                                                </select>
                                            </div>
                                            <div class="field">
                                                <label style="display:block;font-weight:500;margin-bottom:4px;"><?php esc_html_e('Nhãn', 'flavor'); ?></label>
                                                <input type="text" name="surcharges[<?php echo $i; ?>][label]" value="<?php echo esc_attr($s->label ?? ''); ?>" placeholder="VD: TE 6-10 tuổi" style="width:100%;">
                                            </div>
                                            <div class="field vie-age-fields" style="<?php echo ($s->surcharge_type ?? '') !== 'child' ? 'display:none;' : ''; ?>">
                                                <label style="display:block;font-weight:500;margin-bottom:4px;"><?php esc_html_e('Tuổi', 'flavor'); ?></label>
                                                <div style="display:flex;gap:4px;align-items:center;">
                                                    <input type="number" name="surcharges[<?php echo $i; ?>][min_age]" value="<?php echo esc_attr($s->min_age ?? ''); ?>" placeholder="Từ" min="0" max="17" style="width:60px;">
                                                    <span>-</span>
                                                    <input type="number" name="surcharges[<?php echo $i; ?>][max_age]" value="<?php echo esc_attr($s->max_age ?? ''); ?>" placeholder="Đến" min="0" max="17" style="width:60px;">
                                                </div>
                                            </div>
                                            <div class="field">
                                                <label style="display:block;font-weight:500;margin-bottom:4px;"><?php esc_html_e('Số tiền (VNĐ)', 'flavor'); ?></label>
                                                <input type="number" name="surcharges[<?php echo $i; ?>][amount]" value="<?php echo esc_attr($s->amount ?? 0); ?>" min="0" step="1000" style="width:100%;">
                                            </div>
                                            <div class="field" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding-top:20px;">
                                                <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="surcharges[<?php echo $i; ?>][is_per_night]" value="1" <?php checked($s->is_per_night ?? 0, 1); ?>> /đêm</label>
                                                <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="surcharges[<?php echo $i; ?>][applies_to_room]" value="1" <?php checked($s->applies_to_room ?? 0, 1); ?>> Room</label>
                                                <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="surcharges[<?php echo $i; ?>][applies_to_combo]" value="1" <?php checked($s->applies_to_combo ?? 0, 1); ?>> Combo</label>
                                            </div>
                                            <input type="hidden" name="surcharges[<?php echo $i; ?>][sort_order]" value="<?php echo $i; ?>">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" id="vie-add-surcharge" class="button">
                                <?php esc_html_e('+ Thêm phụ thu', 'flavor'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Chính sách -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php esc_html_e('Chính sách', 'flavor'); ?></h2></div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th><label for="price_includes"><?php esc_html_e('Giá đã bao gồm', 'flavor'); ?></label></th>
                                    <td>
                                        <textarea name="price_includes" id="price_includes" class="large-text" rows="3"
                                                  placeholder="<?php esc_attr_e('VD: Bữa sáng cho 2 người, WiFi miễn phí...', 'flavor'); ?>"><?php echo esc_textarea($room->price_includes ?? ''); ?></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cancellation_policy"><?php esc_html_e('Chính sách hủy', 'flavor'); ?></label></th>
                                    <td>
                                        <textarea name="cancellation_policy" id="cancellation_policy" class="large-text" rows="3"
                                                  placeholder="<?php esc_attr_e('VD: Miễn phí hủy trước 24h...', 'flavor'); ?>"><?php echo esc_textarea($room->cancellation_policy ?? ''); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- ============================================================
                     SIDEBAR
                ============================================================ -->
                <div id="postbox-container-1" class="postbox-container">

                    <!-- Publish -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php esc_html_e('Xuất bản', 'flavor'); ?></h2></div>
                        <div class="inside">
                            <p>
                                <label for="status"><strong><?php esc_html_e('Trạng thái', 'flavor'); ?></strong></label>
                                <select name="status" id="status" class="widefat" style="margin-top:4px;">
                                    <option value="active" <?php selected($room->status ?? 'active', 'active'); ?>>
                                        <?php esc_html_e('Hoạt động', 'flavor'); ?>
                                    </option>
                                    <option value="inactive" <?php selected($room->status ?? '', 'inactive'); ?>>
                                        <?php esc_html_e('Tạm ẩn', 'flavor'); ?>
                                    </option>
                                </select>
                            </p>
                            <p>
                                <label for="sort_order"><strong><?php esc_html_e('Thứ tự', 'flavor'); ?></strong></label>
                                <input type="number" name="sort_order" id="sort_order" class="widefat" style="margin-top:4px;"
                                       value="<?php echo esc_attr($room->sort_order ?? 0); ?>" min="0">
                            </p>
                            <hr>
                            <button type="submit" id="vie-save-room" class="button button-primary button-large widefat">
                                <?php echo $is_edit ? esc_html__('Cập nhật', 'flavor') : esc_html__('Thêm mới', 'flavor'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Ảnh đại diện -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php esc_html_e('Ảnh đại diện', 'flavor'); ?></h2></div>
                        <div class="inside">
                            <input type="hidden" name="featured_image_id" id="featured_image_id" 
                                   value="<?php echo esc_attr($room->featured_image_id ?? 0); ?>">
                            <div id="featured-image-preview" style="margin-bottom:10px;">
                                <?php if (!empty($room->featured_image_id)) : ?>
                                    <?php echo wp_get_attachment_image($room->featured_image_id, 'medium'); ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="select-featured-image" class="button">
                                <?php esc_html_e('Chọn ảnh', 'flavor'); ?>
                            </button>
                            <button type="button" id="remove-featured-image" class="button" 
                                    style="<?php echo empty($room->featured_image_id) ? 'display:none;' : ''; ?>">
                                <?php esc_html_e('Xóa', 'flavor'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Gallery -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php esc_html_e('Gallery', 'flavor'); ?></h2></div>
                        <div class="inside">
                            <input type="hidden" name="gallery_ids" id="gallery_ids" 
                                   value="<?php echo esc_attr(wp_json_encode($gallery_ids)); ?>">
                            <div id="vie-gallery-preview" class="vie-gallery" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                                <?php foreach ($gallery_ids as $img_id) : 
                                    $img_url = wp_get_attachment_image_url($img_id, 'thumbnail');
                                    if ($img_url) : ?>
                                    <div class="vie-gallery-item" data-id="<?php echo esc_attr($img_id); ?>" style="position:relative;width:80px;height:80px;">
                                        <img src="<?php echo esc_url($img_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">
                                        <button type="button" class="vie-remove-gallery-item" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#dc2626;color:#fff;border:none;cursor:pointer;font-size:14px;line-height:1;">&times;</button>
                                    </div>
                                <?php endif; endforeach; ?>
                            </div>
                            <button type="button" id="vie-add-gallery-images" class="button">
                                <?php esc_html_e('Thêm ảnh', 'flavor'); ?>
                            </button>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </form>
</div>

<!-- ====================================================================
     SURCHARGE TEMPLATE
==================================================================== -->
<script type="text/template" id="vie-surcharge-template">
<div class="vie-repeater-item" data-index="{{index}}" style="border:1px solid #dcdcde;padding:12px;margin-bottom:10px;border-radius:4px;background:#f9f9f9;">
    <div class="vie-repeater-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <strong><?php esc_html_e('Phụ thu', 'flavor'); ?> #<span class="row-number">{{number}}</span></strong>
        <button type="button" class="vie-remove-surcharge button-link" style="color:#b32d2e;">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
    <div class="vie-repeater-body" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:10px;">
        <div class="field">
            <label style="display:block;font-weight:500;margin-bottom:4px;"><?php esc_html_e('Loại', 'flavor'); ?></label>
            <select name="surcharges[{{index}}][surcharge_type]" class="surcharge-type-select" style="width:100%;">
                <option value="child"><?php esc_html_e('Trẻ em', 'flavor'); ?></option>
                <option value="adult"><?php esc_html_e('NL thêm', 'flavor'); ?></option>
                <option value="extra_bed"><?php esc_html_e('Giường phụ', 'flavor'); ?></option>
                <option value="breakfast"><?php esc_html_e('Bữa sáng', 'flavor'); ?></option>
            </select>
        </div>
        <div class="field">
            <label style="display:block;font-weight:500;margin-bottom:4px;"><?php esc_html_e('Nhãn', 'flavor'); ?></label>
            <input type="text" name="surcharges[{{index}}][label]" placeholder="VD: TE 6-10 tuổi" style="width:100%;">
        </div>
        <div class="field vie-age-fields">
            <label style="display:block;font-weight:500;margin-bottom:4px;"><?php esc_html_e('Tuổi', 'flavor'); ?></label>
            <div style="display:flex;gap:4px;align-items:center;">
                <input type="number" name="surcharges[{{index}}][min_age]" placeholder="Từ" min="0" max="17" style="width:60px;">
                <span>-</span>
                <input type="number" name="surcharges[{{index}}][max_age]" placeholder="Đến" min="0" max="17" style="width:60px;">
            </div>
        </div>
        <div class="field">
            <label style="display:block;font-weight:500;margin-bottom:4px;"><?php esc_html_e('Số tiền (VNĐ)', 'flavor'); ?></label>
            <input type="number" name="surcharges[{{index}}][amount]" min="0" step="1000" style="width:100%;">
        </div>
        <div class="field" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding-top:20px;">
            <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="surcharges[{{index}}][is_per_night]" value="1" checked> /đêm</label>
            <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="surcharges[{{index}}][applies_to_room]" value="1" checked> Room</label>
            <label style="display:flex;align-items:center;gap:4px;"><input type="checkbox" name="surcharges[{{index}}][applies_to_combo]" value="1" checked> Combo</label>
        </div>
        <input type="hidden" name="surcharges[{{index}}][sort_order]" value="{{index}}">
    </div>
</div>
</script>

<!-- ====================================================================
     JAVASCRIPT
==================================================================== -->
<script>
jQuery(function($) {
    var featuredFrame, galleryFrame;
    var surchargeIndex = <?php echo !empty($surcharges) ? count($surcharges) : 0; ?>;

    // =========================================================================
    // FEATURED IMAGE
    // =========================================================================
    $('#select-featured-image').on('click', function(e) {
        e.preventDefault();

        if (featuredFrame) {
            featuredFrame.open();
            return;
        }

        featuredFrame = wp.media({
            title: '<?php esc_html_e('Chọn ảnh đại diện', 'flavor'); ?>',
            button: { text: '<?php esc_html_e('Chọn', 'flavor'); ?>' },
            multiple: false
        });

        featuredFrame.on('select', function() {
            var attachment = featuredFrame.state().get('selection').first().toJSON();
            $('#featured_image_id').val(attachment.id);
            $('#featured-image-preview').html('<img src="' + attachment.url + '" style="max-width:100%;">');
            $('#remove-featured-image').show();
        });

        featuredFrame.open();
    });

    $('#remove-featured-image').on('click', function() {
        $('#featured_image_id').val('');
        $('#featured-image-preview').empty();
        $(this).hide();
    });

    // =========================================================================
    // GALLERY
    // =========================================================================
    function updateGalleryIds() {
        var ids = [];
        $('#vie-gallery-preview .vie-gallery-item').each(function() {
            ids.push($(this).data('id'));
        });
        $('#gallery_ids').val(JSON.stringify(ids));
    }

    $('#vie-add-gallery-images').on('click', function(e) {
        e.preventDefault();

        if (galleryFrame) {
            galleryFrame.open();
            return;
        }

        galleryFrame = wp.media({
            title: '<?php esc_html_e('Chọn ảnh gallery', 'flavor'); ?>',
            button: { text: '<?php esc_html_e('Thêm vào gallery', 'flavor'); ?>' },
            multiple: true
        });

        galleryFrame.on('select', function() {
            var attachments = galleryFrame.state().get('selection').toJSON();
            attachments.forEach(function(attachment) {
                var html = '<div class="vie-gallery-item" data-id="' + attachment.id + '" style="position:relative;width:80px;height:80px;">' +
                    '<img src="' + (attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">' +
                    '<button type="button" class="vie-remove-gallery-item" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#dc2626;color:#fff;border:none;cursor:pointer;font-size:14px;line-height:1;">&times;</button>' +
                '</div>';
                $('#vie-gallery-preview').append(html);
            });
            updateGalleryIds();
        });

        galleryFrame.open();
    });

    $(document).on('click', '.vie-remove-gallery-item', function() {
        $(this).closest('.vie-gallery-item').remove();
        updateGalleryIds();
    });

    // =========================================================================
    // SURCHARGES
    // =========================================================================
    $('#vie-add-surcharge').on('click', function() {
        var template = $('#vie-surcharge-template').html();
        template = template.replace(/{{index}}/g, surchargeIndex);
        template = template.replace(/{{number}}/g, surchargeIndex + 1);
        $('#vie-surcharges-container').append(template);
        surchargeIndex++;
        updateRowNumbers();
    });

    $(document).on('click', '.vie-remove-surcharge', function() {
        $(this).closest('.vie-repeater-item').remove();
        updateRowNumbers();
    });

    $(document).on('change', '.surcharge-type-select', function() {
        var $item = $(this).closest('.vie-repeater-item');
        if ($(this).val() === 'child') {
            $item.find('.vie-age-fields').show();
        } else {
            $item.find('.vie-age-fields').hide();
        }
    });

    function updateRowNumbers() {
        $('#vie-surcharges-container .vie-repeater-item').each(function(i) {
            $(this).find('.row-number').text(i + 1);
        });
    }

    // =========================================================================
    // FORM SUBMIT
    // =========================================================================
    $('#vie-room-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#vie-save-room');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('<?php esc_html_e('Đang lưu...', 'flavor'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $(this).serialize() + '&action=vie_save_room',
            success: function(res) {
                if (res.success) {
                    alert(res.data.message);
                    if (!<?php echo $is_edit ? 'true' : 'false'; ?>) {
                        window.location.href = '<?php echo admin_url('admin.php?page=vie-hotel-rooms&action=edit&room_id='); ?>' + res.data.room_id;
                    }
                } else {
                    alert(res.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'flavor'); ?>');
                }
                $btn.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert('<?php esc_html_e('Lỗi kết nối', 'flavor'); ?>');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>

<style>
.required { color: #dc2626; }
</style>
