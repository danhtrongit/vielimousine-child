<?php
/**
 * Admin View: Room Form - Sử dụng WP postbox style
 * @package VieHotelRooms
 */
if (!defined('ABSPATH')) exit;

$is_edit = !empty($room);
$page_title = $is_edit ? __('Chỉnh sửa Loại Phòng', 'viechild') : __('Thêm Loại Phòng mới', 'viechild');
$gallery_ids = $is_edit && $room->gallery_ids ? json_decode($room->gallery_ids, true) : [];
if (!is_array($gallery_ids)) $gallery_ids = [];
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms'); ?>" class="page-title-action"><?php _e('← Quay lại', 'viechild'); ?></a>
    <hr class="wp-header-end">
    
    <form id="vie-room-form" method="post">
        <input type="hidden" name="room_id" value="<?php echo esc_attr($room_id); ?>">
        <?php wp_nonce_field('vie_hotel_rooms_nonce', 'nonce'); ?>
        
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <!-- Main Column -->
                <div id="post-body-content">
                    <!-- Title -->
                    <div id="titlediv">
                        <div id="titlewrap">
                            <label class="screen-reader-text" for="name"><?php _e('Tên phòng', 'viechild'); ?></label>
                            <input type="text" name="name" id="name" size="30" value="<?php echo esc_attr($room->name ?? ''); ?>" placeholder="<?php _e('Nhập tên loại phòng', 'viechild'); ?>" autocomplete="off" required>
                        </div>
                    </div>
                </div>
                
                <div id="postbox-container-1" class="postbox-container">
                    <!-- Publish Box -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Xuất bản', 'viechild'); ?></h2></div>
                        <div class="inside">
                            <div class="misc-pub-section">
                                <label for="status"><?php _e('Trạng thái:', 'viechild'); ?></label>
                                <select name="status" id="status">
                                    <option value="active" <?php selected($room->status ?? '', 'active'); ?>><?php _e('Hoạt động', 'viechild'); ?></option>
                                    <option value="inactive" <?php selected($room->status ?? '', 'inactive'); ?>><?php _e('Tạm ngừng', 'viechild'); ?></option>
                                    <option value="draft" <?php selected($room->status ?? '', 'draft'); ?>><?php _e('Nháp', 'viechild'); ?></option>
                                </select>
                            </div>
                            <div class="misc-pub-section">
                                <label for="sort_order"><?php _e('Thứ tự:', 'viechild'); ?></label>
                                <input type="number" name="sort_order" id="sort_order" value="<?php echo esc_attr($room->sort_order ?? 0); ?>" min="0" class="small-text">
                            </div>
                            <div id="major-publishing-actions">
                                <?php if ($is_edit) : ?>
                                <div id="delete-action">
                                    <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-calendar&room_id=' . $room_id); ?>" class="button"><?php _e('Lịch giá', 'viechild'); ?></a>
                                </div>
                                <?php endif; ?>
                                <div id="publishing-action">
                                    <button type="submit" class="button button-primary button-large" id="vie-save-room"><?php echo $is_edit ? __('Cập nhật', 'viechild') : __('Lưu phòng', 'viechild'); ?></button>
                                </div>
                                <div class="clear"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hotel Select -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Khách sạn', 'viechild'); ?> <span class="required">*</span></h2></div>
                        <div class="inside">
                            <select name="hotel_id" id="hotel_id" style="width:100%" required>
                                <option value=""><?php _e('-- Chọn khách sạn --', 'viechild'); ?></option>
                                <?php foreach ($hotels as $hotel) : ?>
                                <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($hotel_id, $hotel->ID); ?>><?php echo esc_html($hotel->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Price -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Giá cơ bản', 'viechild'); ?></h2></div>
                        <div class="inside">
                            <input type="number" name="base_price" id="base_price" value="<?php echo esc_attr($room->base_price ?? 0); ?>" min="0" step="1000" style="width:100%;font-size:16px;padding:8px">
                            <p class="description"><?php _e('Giá mặc định khi chưa set lịch (VNĐ)', 'viechild'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Featured Image -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Ảnh đại diện', 'viechild'); ?></h2></div>
                        <div class="inside">
                            <input type="hidden" name="featured_image_id" id="featured_image_id" value="<?php echo esc_attr($room->featured_image_id ?? 0); ?>">
                            <div class="vie-image-preview" id="vie-featured-image-preview">
                                <?php if (!empty($room->featured_image_id)) : ?>
                                <img src="<?php echo esc_url(wp_get_attachment_image_url($room->featured_image_id, 'medium')); ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <p>
                                <button type="button" class="button" id="vie-select-featured-image"><?php _e('Chọn ảnh', 'viechild'); ?></button>
                                <button type="button" class="button button-link-delete" id="vie-remove-featured-image" style="<?php echo empty($room->featured_image_id) ? 'display:none;' : ''; ?>"><?php _e('Xóa', 'viechild'); ?></button>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Gallery -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Gallery', 'viechild'); ?></h2></div>
                        <div class="inside">
                            <input type="hidden" name="gallery_ids" id="gallery_ids" value="<?php echo esc_attr(wp_json_encode($gallery_ids)); ?>">
                            <div class="vie-gallery" id="vie-gallery-preview">
                                <?php foreach ($gallery_ids as $img_id) : $img_url = wp_get_attachment_image_url($img_id, 'thumbnail'); if ($img_url) : ?>
                                <div class="vie-gallery-item" data-id="<?php echo esc_attr($img_id); ?>">
                                    <img src="<?php echo esc_url($img_url); ?>" alt="">
                                    <button type="button" class="remove">&times;</button>
                                </div>
                                <?php endif; endforeach; ?>
                            </div>
                            <button type="button" class="button" id="vie-add-gallery-images"><?php _e('Thêm ảnh', 'viechild'); ?></button>
                        </div>
                    </div>
                </div>
                
                <div id="postbox-container-2" class="postbox-container">
                    <!-- Description -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Mô tả', 'viechild'); ?></h2></div>
                        <div class="inside">
                            <p><textarea name="short_description" rows="2" style="width:100%" placeholder="<?php _e('Mô tả ngắn...', 'viechild'); ?>"><?php echo esc_textarea($room->short_description ?? ''); ?></textarea></p>
                            <?php wp_editor($room->description ?? '', 'description', ['textarea_name' => 'description', 'textarea_rows' => 8, 'media_buttons' => true, 'teeny' => true]); ?>
                        </div>
                    </div>
                    
                    <!-- Room Details -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Chi tiết phòng', 'viechild'); ?></h2></div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tr><th><label for="room_size"><?php _e('Diện tích', 'viechild'); ?></label></th>
                                    <td><input type="text" name="room_size" id="room_size" value="<?php echo esc_attr($room->room_size ?? ''); ?>" class="regular-text" placeholder="VD: 35 m²"></td></tr>
                                <tr><th><label for="bed_type"><?php _e('Loại giường', 'viechild'); ?></label></th>
                                    <td><input type="text" name="bed_type" id="bed_type" value="<?php echo esc_attr($room->bed_type ?? ''); ?>" class="regular-text" placeholder="VD: 1 King bed"></td></tr>
                                <tr><th><label for="view_type"><?php _e('View', 'viechild'); ?></label></th>
                                    <td><select name="view_type" id="view_type">
                                        <option value="">--</option>
                                        <option value="ocean" <?php selected($room->view_type ?? '', 'ocean'); ?>>Ocean</option>
                                        <option value="city" <?php selected($room->view_type ?? '', 'city'); ?>>City</option>
                                        <option value="garden" <?php selected($room->view_type ?? '', 'garden'); ?>>Garden</option>
                                        <option value="pool" <?php selected($room->view_type ?? '', 'pool'); ?>>Pool</option>
                                    </select></td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Occupancy -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Sức chứa', 'viechild'); ?></h2></div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tr><th><label><?php _e('Người tiêu chuẩn', 'viechild'); ?></label></th>
                                    <td><input type="number" name="base_occupancy" value="<?php echo esc_attr($room->base_occupancy ?? 2); ?>" min="1" max="10" class="small-text"> <span class="description"><?php _e('Đã bao gồm trong giá', 'viechild'); ?></span></td></tr>
                                <tr><th><label><?php _e('Tối đa NL / TE', 'viechild'); ?></label></th>
                                    <td><input type="number" name="max_adults" value="<?php echo esc_attr($room->max_adults ?? 2); ?>" min="1" max="10" class="small-text"> / 
                                        <input type="number" name="max_children" value="<?php echo esc_attr($room->max_children ?? 2); ?>" min="0" max="10" class="small-text"></td></tr>
                                <tr><th><label><?php _e('Tổng số phòng', 'viechild'); ?></label></th>
                                    <td><input type="number" name="total_rooms" value="<?php echo esc_attr($room->total_rooms ?? 1); ?>" min="1" max="999" class="small-text"> <span class="description"><?php _e('Số phòng vật lý', 'viechild'); ?></span></td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Surcharges -->
                    <div class="postbox">
                        <div class="postbox-header"><h2><?php _e('Phụ thu', 'viechild'); ?></h2></div>
                        <div class="inside">
                            <p class="description"><?php _e('Thiết lập phụ thu: Extra bed, trẻ em theo tuổi, người lớn thêm...', 'viechild'); ?></p>
                            <div id="vie-surcharges-container">
                                <?php if (!empty($surcharges)) : foreach ($surcharges as $i => $s) : ?>
                                <div class="vie-repeater-item" data-index="<?php echo $i; ?>">
                                    <div class="vie-repeater-header">
                                        <strong><?php _e('Phụ thu', 'viechild'); ?> #<span class="row-number"><?php echo $i + 1; ?></span></strong>
                                        <button type="button" class="button-link vie-remove-surcharge" style="color:#b32d2e"><span class="dashicons dashicons-no-alt"></span></button>
                                    </div>
                                    <div class="vie-repeater-body">
                                        <div class="field">
                                            <label><?php _e('Loại', 'viechild'); ?></label>
                                            <select name="surcharges[<?php echo $i; ?>][surcharge_type]" class="surcharge-type-select">
                                                <option value="child" <?php selected($s->surcharge_type, 'child'); ?>><?php _e('Trẻ em', 'viechild'); ?></option>
                                                <option value="adult" <?php selected($s->surcharge_type, 'adult'); ?>><?php _e('NL thêm', 'viechild'); ?></option>
                                                <option value="extra_bed" <?php selected($s->surcharge_type, 'extra_bed'); ?>><?php _e('Giường phụ', 'viechild'); ?></option>
                                                <option value="breakfast" <?php selected($s->surcharge_type, 'breakfast'); ?>><?php _e('Bữa sáng', 'viechild'); ?></option>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label><?php _e('Nhãn', 'viechild'); ?></label>
                                            <input type="text" name="surcharges[<?php echo $i; ?>][label]" value="<?php echo esc_attr($s->label); ?>" placeholder="VD: TE 6-10 tuổi">
                                        </div>
                                        <div class="field vie-age-fields" style="<?php echo $s->surcharge_type !== 'child' ? 'display:none;' : ''; ?>">
                                            <label><?php _e('Tuổi', 'viechild'); ?></label>
                                            <div class="inline-fields">
                                                <input type="number" name="surcharges[<?php echo $i; ?>][min_age]" value="<?php echo esc_attr($s->min_age); ?>" placeholder="Từ" min="0" max="17">
                                                <span>-</span>
                                                <input type="number" name="surcharges[<?php echo $i; ?>][max_age]" value="<?php echo esc_attr($s->max_age); ?>" placeholder="Đến" min="0" max="17">
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label><?php _e('Số tiền', 'viechild'); ?></label>
                                            <input type="number" name="surcharges[<?php echo $i; ?>][amount]" value="<?php echo esc_attr($s->amount); ?>" min="0" step="1000">
                                        </div>
                                        <div class="field checkbox-group">
                                            <label><input type="checkbox" name="surcharges[<?php echo $i; ?>][is_per_night]" value="1" <?php checked($s->is_per_night, 1); ?>> <?php _e('/đêm', 'viechild'); ?></label>
                                            <label><input type="checkbox" name="surcharges[<?php echo $i; ?>][applies_to_room]" value="1" <?php checked($s->applies_to_room, 1); ?>> Room</label>
                                            <label><input type="checkbox" name="surcharges[<?php echo $i; ?>][applies_to_combo]" value="1" <?php checked($s->applies_to_combo, 1); ?>> Combo</label>
                                        </div>
                                        <input type="hidden" name="surcharges[<?php echo $i; ?>][sort_order]" value="<?php echo $i; ?>">
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                            <p><button type="button" class="button" id="vie-add-surcharge"><?php _e('+ Thêm phụ thu', 'viechild'); ?></button></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="text/template" id="vie-surcharge-template">
<div class="vie-repeater-item" data-index="{{index}}">
    <div class="vie-repeater-header">
        <strong><?php _e('Phụ thu', 'viechild'); ?> #<span class="row-number">{{number}}</span></strong>
        <button type="button" class="button-link vie-remove-surcharge" style="color:#b32d2e"><span class="dashicons dashicons-no-alt"></span></button>
    </div>
    <div class="vie-repeater-body">
        <div class="field">
            <label><?php _e('Loại', 'viechild'); ?></label>
            <select name="surcharges[{{index}}][surcharge_type]" class="surcharge-type-select">
                <option value="child"><?php _e('Trẻ em', 'viechild'); ?></option>
                <option value="adult"><?php _e('NL thêm', 'viechild'); ?></option>
                <option value="extra_bed"><?php _e('Giường phụ', 'viechild'); ?></option>
                <option value="breakfast"><?php _e('Bữa sáng', 'viechild'); ?></option>
            </select>
        </div>
        <div class="field">
            <label><?php _e('Nhãn', 'viechild'); ?></label>
            <input type="text" name="surcharges[{{index}}][label]" placeholder="VD: TE 6-10 tuổi">
        </div>
        <div class="field vie-age-fields">
            <label><?php _e('Tuổi', 'viechild'); ?></label>
            <div class="inline-fields">
                <input type="number" name="surcharges[{{index}}][min_age]" placeholder="Từ" min="0" max="17">
                <span>-</span>
                <input type="number" name="surcharges[{{index}}][max_age]" placeholder="Đến" min="0" max="17">
            </div>
        </div>
        <div class="field">
            <label><?php _e('Số tiền', 'viechild'); ?></label>
            <input type="number" name="surcharges[{{index}}][amount]" min="0" step="1000">
        </div>
        <div class="field checkbox-group">
            <label><input type="checkbox" name="surcharges[{{index}}][is_per_night]" value="1" checked> <?php _e('/đêm', 'viechild'); ?></label>
            <label><input type="checkbox" name="surcharges[{{index}}][applies_to_room]" value="1" checked> Room</label>
            <label><input type="checkbox" name="surcharges[{{index}}][applies_to_combo]" value="1" checked> Combo</label>
        </div>
        <input type="hidden" name="surcharges[{{index}}][sort_order]" value="{{index}}">
    </div>
</div>
</script>
