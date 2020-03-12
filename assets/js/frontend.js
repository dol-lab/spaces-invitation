/**
 * Plugin Template frontend js.
 *
 *  @package WordPress Plugin Template/JS
 */

jQuery(function($) {
    return function() {
        /**
         * Activates / Deactivates the invitation link (calls the API).
         */
        var send = function(activate) {
            return $.post(INVITATION_ADMIN_URL.url, {
                action: 'invitation_link',
                activate: activate
            });
        };

        var input = $('.invitation-toggle input.switch-input');
        var linkBox = $('.spaces-invitation-link-box');
        $('.invitation-toggle').on('click', function(e){
            var self = $(this);
            var startValue = input.prop('checked');
            input.prop('checked', !startValue);
            linkBox.toggleClass('disabled', startValue);// show / hide the invitation link

            self.removeClass('success');
            send(!startValue).then(function(){
                self.addClass('success');
            }).fail(function(){
                // reset everything
                input.prop('checked', startValue);
                linkBox.toggleClass('disabled', !startValue);
                self.addClass('shake');
				window.setTimeout(function () {
					self.removeClass('shake');
				}, 500);
            });

            return false;
        });
        $('.invitation-link').on('click', function(){
            $(this).select();
        });

        var value = !!input.attr('data-checked');
        input.prop('checked', value);
        linkBox.toggleClass('disabled', !value);
    };
}(jQuery));
