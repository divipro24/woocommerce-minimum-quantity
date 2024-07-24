jQuery(function($) {
    var $wp_inline_edit = inlineEditPost.edit;
    inlineEditPost.edit = function(id) {
        $wp_inline_edit.apply(this, arguments);
        var post_id = 0;
        if (typeof(id) == 'object') {
            post_id = parseInt(this.getId(id));
        }
        if (post_id > 0) {
            var $edit_row = $('#edit-' + post_id);
            var $post_row = $('#post-' + post_id);
            var min_quantity = $post_row.find('.hidden[data-min_quantity]').data('min_quantity');
            $edit_row.find('input[name="_custom_minimum_quantity"]').val(min_quantity);
        }
    }
});