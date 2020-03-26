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

        var textOptions = {
            true: 'Invitation Link enabled',
            false: 'Invitation Link disabled',
        };

        var input = $('.invitation-toggle input.switch-input');
        var linkBox = $('.spaces-invitation-link-box');
        var toggle = $('.invitation-toggle');
        var text = $('.invitation-text');
        var setText = function(enabled){
            text.text(textOptions[enabled]);
        };

        $('.invitation-label').on('click', function(){
            toggle.hasClass('link-disabled') || toggle.click();
        });
        toggle.on('click', function(e){
            var self = toggle;
            var startValue = input.prop('checked');
            input.prop('checked', !startValue);
            setText(!startValue);
            linkBox.toggleClass('link-disabled', startValue);// show / hide the invitation link

            self.removeClass('success');
            send(!startValue).then(function(){
                self.addClass('success');
            }).fail(function(){
                // reset everything
                input.prop('checked', startValue);
                setText(startValue);
                linkBox.toggleClass('link-disabled', !startValue);
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
        setText(value);
        linkBox.toggleClass('link-disabled', !value);
    };
}(jQuery));
