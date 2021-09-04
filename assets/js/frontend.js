/**
 * Plugin Template frontend js.
 *
 *  @package WordPress Plugin Template/JS
 */

jQuery(
  (function ($) {
    var setInitialChoice = function () {
      Array.from(
        document.querySelectorAll('input[name=invitation-status][checked]')
      ).forEach(function (input) {
        input.checked = true
      })
    }

    var send = function (url, action, nonce, data) {
      return $.post(
        url,
        Object.assign({}, data, {
          action: action,
          _wpnonce: nonce,
        })
      )
    }

    var addPasswordEditHook = function () {
      var currentToken = INVITATION_TOKEN.token

      let edit = document.querySelector('.invitation-edit-link')
      if (!edit) {
        return
      }

      edit.addEventListener('click', function () {
        var node = document.createElement('div')
        var input = document.createElement('input')
        var button = document.createElement('button')
        button.classList.add('button')
        button.textContent = 'Save'
        node.classList.add('spaces-invitation-edit-modal')
        input.type = 'text'
        input.value = currentToken
        node.appendChild(input)
        node.appendChild(button)
        var removeSelf = function (event) {
          if (![node, input, button, this].includes(event.target)) {
            document.removeEventListener('click', removeSelf)
            node.remove()
          }
        }.bind(this)

        button.addEventListener('click', function () {
          var value = input.value
          send(
            INVITATION_ADMIN_URL.url,
            'invitation_update_token',
            INVITATION_NONCES.invitation_update_token,
            {
              token: value,
            }
          ).then(function (response) {
            node.remove()
            document.querySelector('.invitation-link').value = response.link
            currentToken = value
          })
        })

        document.addEventListener('click', removeSelf)

        this.parentNode.appendChild(node)

        input.select()
      })
    }

    setInitialChoice()

    var inputs = $('input.invitation-input.radio-input')

    var selectedInput = inputs.filter('[checked]')
    inputs.on('change', function () {
      var self = $(this)
      send(
        INVITATION_ADMIN_URL.url,
        'change_invitation_option',
        INVITATION_NONCES.invitation_link,
        {
          option: this.value,
        }
      ).success(function (response) {
        if (false === response.success) {
          selectedInput[0].checked = true
          selectedInput.trigger('change')
        } else {
          selectedInput = self
        }
      })
    })

    document.addEventListener('spacePrivacyChanged', function (event) {
      send(
        INVITATION_ADMIN_URL.url,
        'get_disabled_options',
        INVITATION_NONCES.get_disabled_options,
        {}
      ).success(function (response) {
        if (!response.disabled_options || !response.active_option) {
          console.log('error requesting get_disabled_options')
          return
        }
        inputs.each(function () {
          this.disabled = response.disabled_options.includes(this.value)
          if (this.value === response.active_option) {
            this.checked = true
            selectedInput = $(this)
            selectedInput.trigger('change')
          }
        })
      })
    })

    window.addEventListener('notification-toggle', function (event) {
      $(`.notification-toggle-wrapper-${event.detail.blog_id}`).toggleClass('hide', event.detail.task == 'subscribe')
    })

    addPasswordEditHook()
  })(jQuery)
)
