jQuery(document).ready(function() { 
    
    jQuery('.btn-file .button-name').html('Choose');
    jQuery('.btn-file .button-name').css('font-weight', 'bold');
    jQuery('.btn-file :file').on('fileselect', function(event, label) {
        jQuery('.filename').html(label);
    });

    jQuery(document).on('change', '.btn-file :file', function () {
        jQuery('.filename').css('color', '#575c63');
        var input = jQuery(this),
                label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
        input.trigger('fileselect', [label]);
    });
    jQuery('.upload_photo_form').on('submit', function (e) {
        if (jQuery('.btn-file :file').val() == '') {
            e.preventDefault();
            jQuery('.filename').css('color', '#d00');
            jQuery('.filename').html('Please choose image.');
        }
    });
    
    ajax_ready = true;
    var pageNumber = data.page;
    
    function loadArticle(page_number, select, count){   
        var item_photo = jQuery.cookie('item-photo');
        if(jQuery.isNumeric(item_photo) && parseInt(item_photo) > 20){
            count = Math.ceil(parseInt(item_photo) / 10) * 10;
            page_number = 1;
            pageNumber = Math.ceil(parseInt(item_photo) / 10) + 1;
        }
        jQuery.removeCookie('item-photo'); 
        jQuery('#inifiniteLoader').show();
        selector = select;
        ajax_ready = false;
        jQuery.ajax({
            url: data.ajax_url,
            type: 'POST',
            data: {
                    action: 'infinite_scroll',
                    page_no: page_number,
                    post_count: count
            },
            success: function(html){
                ajax_ready = true;
                jQuery(selector).append(html);
                if(item_photo){
                    jQuery('html, body').animate({
                        scrollTop : jQuery('#list-photo li:last').offset().top + jQuery('#list-photo li').height() - jQuery(window).height()
                    }, 0);
                }
                jQuery('#inifiniteLoader').hide();
            }
        });
        return false;
    }
    
    loadArticle(1, '#photo-content', 20);
        
    jQuery(window).scroll(function(){
        if(parseInt(data.max_pages) >= parseInt(pageNumber) && ajax_ready){
            if (jQuery(window).scrollTop() + jQuery(window).height() > jQuery('#list-photo li:last').offset().top + jQuery('#list-photo li').height() + 50){
                loadArticle(pageNumber, '#list-photo', 10);
                pageNumber++;
            }
        }
    });
   
    jQuery(document).on( "click", '#list-photo li', function(){
        jQuery.cookie('item-photo', jQuery(this).data('item'));
    })
   
})
