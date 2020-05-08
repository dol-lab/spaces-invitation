/**
 * Plugin Template frontend js.
 *
 *  @package WordPress Plugin Template/JS
 */

jQuery(function($) {
    var Void = function(){};
    var dummySwitch = {toggle: Void, enable: Void, disable: Void};
    var createSwitch = function(label, toggleClass, endpoint){
        var toggle = label.querySelector(toggleClass);
        var input = label.querySelector('input.switch-input');
        var state = !input.getAttribute('data-checked');
        input.checked = state;
        label.addEventListener('click', function(event){
            event.preventDefault();
        });
        var toggleFn = function(){
            input.checked = !state;
            toggle.classList.remove('success');
            endpoint(!state).then(function(){
                state = !state;
                toggle.classList.add('success');
            }).fail(function(){
                input.checked = state;
                toggle.classList.add('shake');
                window.setTimeout(function () {
					self.classList.remove('shake');
				}, 500);
            });
        };

        label.addEventListener('click', function(){toggle.click();});
        toggle.addEventListener('click', function(e){e.target === toggle && toggleFn();});
        toggleFn();

        return {
            toggle: toggleFn,
            enable: function(){
                !state && toggleFn();
            },
            disable: function(){
                state && toggleFn();
            }
        };
    };

    var createSimpleEndpoint = function(url, action){
        var send = function(){
            send = function(activate) {
                return $.post(url, {
                    action: action,
                    activate: activate
                });
            };
            var dummyPromise = {fail: function(){return dummyPromise;}, then: function(f){f(); return dummyPromise;}};

            return dummyPromise;
        };

        return function(data){
            return send(data);
        };
    };

    var wrapEndpointWithTextUpdate = function(textNode, endpoint, textOptions, onEnable){
        return function(enable){
            textNode.textContent = textOptions[enable];
            var promise = endpoint(enable);
            promise.fail(function(){
                textNode.textContent = textOptions[!enable];
            });
            if(enable)
            {
                promise.then(onEnable);
            }
            return promise;
        };
    };
    
    return function() {
        var invitationSwitch = dummySwitch;
        var registrationSwitch = dummySwitch;
        var invitationEndpoint = wrapEndpointWithTextUpdate(
            document.querySelector('.invitation-label .invitation-text'),
            createSimpleEndpoint(INVITATION_ADMIN_URL.url, 'invitation_link'), INVITATION_TEXT_OPTIONS.invitation,
            function(enable){
                registrationSwitch.disable();
            }
        );
        var selfRegistrationEndpoint = wrapEndpointWithTextUpdate(
            document.querySelector('.self-registration-label .self-registration-text'),
            createSimpleEndpoint(INVITATION_ADMIN_URL.url, 'self_registration'), INVITATION_TEXT_OPTIONS.self_registration,
            function(enable){
                invitationSwitch.disable();
            }
        );
        var invitationLabel = document.querySelector('.invitation-label');
        var registrationLabel = document.querySelector('.self-registration-label');
        if(invitationLabel && !invitationLabel.classList.contains('link-disabled'))
        {
            invitationSwitch = createSwitch(invitationLabel, '.invitation-toggle', invitationEndpoint);
        }
        if(!registrationLabel)
        {
            return;
        }
        registrationSwitch = createSwitch(registrationLabel, '.self-registration-toggle', selfRegistrationEndpoint);

        document.querySelector('.invitation-link').addEventListener('click', function(){
            $(this).select();
        });
        document.addEventListener("spacePrivacyChanged", function (event) {
            if(-2 === event.detail.privacy)
            {
                registrationLabel.classList.add('private');
                registrationSwitch.disable();
            }
            else
            {
                registrationLabel.classList.remove('private');
            }
        });
    };
}(jQuery));
