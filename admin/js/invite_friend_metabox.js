


document.addEventListener('DOMContentLoaded', function(){
    var $ = jQuery;
    $('.form-control').select2(
        {
            tags: true,
            tokenSeparators: [',', ' ']
        }
    );
})