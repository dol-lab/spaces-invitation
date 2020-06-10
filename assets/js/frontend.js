/**
 * Plugin Template frontend js.
 *
 *  @package WordPress Plugin Template/JS
 */

jQuery(function($) {
    var Void = function(){};
    var always = function(value){return function(){return value;};};
    var dummySwitch = {toggle: Void, enable: Void, disable: Void};
    var createSwitch = function(label, toggleClass, endpoint, preventClick){
        preventClick = preventClick || always(false);
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
        toggle.addEventListener('click', function(e){e.target === toggle && !preventClick(label) && toggleFn();});
        toggleFn();

        return {
            toggle: toggleFn,
            enable: function(){
                !state && toggleFn();
            },
            disable: function(){
                state && toggleFn();
            },
            isEnabled: function(){
                return state;
            }
        };
    };

    var createSimpleEndpoint = function(url, action, key, nonce){
        var send = function(){
            send = function(value) {
                var sendMe = {};
                sendMe[key] = value;
                return $.post(url, Object.assign({}, sendMe, {
                    action: action,
                    _wpnonce: nonce
                }));
            };
            var dummyPromise = {fail: function(){return dummyPromise;}, then: function(f){f(); return dummyPromise;}};

            return dummyPromise;
        };

        return function(data){
            return send(data);
        };
    };

    var wrapEndpointWithTextUpdate = function(textNode, endpoint, textOptions, onEnable){
        var onRequest = function(){
            onRequest = function(enable, promise){
                promise.then(function(){
                    onEnable(enable);
                });
            };
        };
        return function(enable){
            textNode.textContent = textOptions[enable];
            var promise = endpoint(enable);
            promise.fail(function(){
                textNode.textContent = textOptions[!enable];
            });
            onRequest(enable, promise);
            return promise;
        };
    };
    
    return function() {
        var invitationSwitch   = dummySwitch;
        var registrationSwitch = dummySwitch;
        var invitationLabel = document.querySelector('.invitation-label');
        var registrationLabel = document.querySelector('.self-registration-label');
        var invitationEndpoint = wrapEndpointWithTextUpdate(
            document.querySelector('.invitation-label .invitation-text'),
            createSimpleEndpoint(INVITATION_ADMIN_URL.url, 'invitation_link', 'activate', INVITATION_NONCES.invitation_link),
            INVITATION_TEXT_OPTIONS.invitation,
            function(enable){
                var method = enable ? 'remove' : 'add';
                Array.from(document.querySelectorAll('.spaces-invitation-box')).forEach(function(node){
                    node.classList[method]('link-disabled');
                });
            }
        );
        var selfRegistrationEndpoint = wrapEndpointWithTextUpdate(
            document.querySelector('.self-registration-label .self-registration-text'),
            createSimpleEndpoint(INVITATION_ADMIN_URL.url, 'self_registration', 'activate', INVITATION_NONCES.self_registration),
            INVITATION_TEXT_OPTIONS.self_registration,
            function(enable){
                invitationLabel && invitationLabel.classList[enable ? 'add' : 'remove']('no-toggle');
                if(enable)
                {
                    invitationSwitch.enable();
                }
            }
        );
        var createInvitationSwitch = function(){
            return createSwitch(invitationLabel, '.invitation-toggle', invitationEndpoint, function(label){
                return label.classList.contains('no-toggle');
            });
        };
        if(invitationLabel)
        {
            invitationSwitch = createInvitationSwitch();
        }
        if(!registrationLabel)
        {
            return;
        }
        registrationSwitch = createSwitch(registrationLabel, '.self-registration-toggle', selfRegistrationEndpoint);

        (document.querySelector('.invitation-link') || {addEventListener: Void}).addEventListener('click', function(){
            this.select();
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

        var saveLinkEndpoint = createSimpleEndpoint(INVITATION_ADMIN_URL.url, 'invitation_update_token', 'token', INVITATION_NONCES.invitation_update_token);
        saveLinkEndpoint();
        var currentToken = INVITATION_TOKEN.token;

        document.querySelector('.invitation-edit-link').addEventListener('click', function(){
            var node = document.createElement('div');
            var input = document.createElement('input');
            var button = document.createElement('button');
            button.classList.add('button');
            button.textContent = 'Save';
            node.classList.add('spaces-invitation-edit-modal');
            input.type = "text";
            input.value = currentToken;
            node.appendChild(input);
            node.appendChild(button);

            var removeSelf = function(event){
                if(![node, input, button, this].includes(event.target))
                {
                    document.removeEventListener('click', removeSelf);
                    node.remove();
                }
            }.bind(this);

            button.addEventListener('click', function(){
                var value = input.value;
                saveLinkEndpoint(value).then(function(response){
                    node.remove();
                    document.querySelector('.invitation-link').textContent = response.link;
                    currentToken = value;
                });
            });

            document.addEventListener('click', removeSelf);

            this.parentNode.appendChild(node);

            input.select();
        });
    };
}(jQuery));
