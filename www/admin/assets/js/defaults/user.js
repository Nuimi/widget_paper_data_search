$(document).on('click', '[data-generate-password]', function()
    {
        $.get('/ajax/generatePassword',
            {},
            function (data)
            {
                data = JSON.parse(data);
                $('#password1').val(data['password']);
                $('#password2').val(data['password']);
            }
        );
    }
);

$(document).on('click', '[data-show-password]', function()
    {
        var pass = $('#' + $(this).data('showPassword'));
        var eye = $('#' + $(this).data('showPassword') + '-eye');

        if (eye.hasClass('fa-eye-slash'))
        {
            pass.attr('type', 'text');
            eye.removeClass('fa-eye-slash');
            eye.addClass('fa-eye');
        } else {
            pass.attr('type', 'password');
            eye.removeClass('fa-eye');
            eye.addClass('fa-eye-slash');
        }
    }
);

let typingTimer;
let doneTypingInterval = 3000;
let $input = $('#surName');

$input.on('keyup', function ()
    {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(doneTyping, doneTypingInterval);
        $input.focusout(doneTyping);
    }
);

$input.on('keydown', function ()
    {
        clearTimeout(typingTimer);
    }
);

function doneTyping ()
{
    let firstName = $('#firstName').val()
    let surName = $input.val();

    $('#userName').val(firstName.toLowerCase() + surName);
}

$(document).on('click', '[data-delete-user]', function()
    {
        $('#modalUserID-delete').val($(this).data('deleteUser'));
        $('#modal-deleteUser').modal('show');
    }
);

$(document).on('click', '[data-delete-user-modal]', function()
    {
        window.location.href = '/user/deleteUser/' + $('#modalUserID-delete').val();
    }
);

$(document).on('click', '[data-denny-entry]', function()
    {
        $.get('/ajax/changeEntry/' + $(this).data('dennyEntry'),
            {},
            function (data)
            {
                $('#editUser-table').html(data);
            }
        );
    }
);

$(document).on('click', '[data-edit-user]', function()
    {
        var $data = $(this).data('editUser');
        $('#id').val($data['id']);
        $('#firstName').val($data['firstName']);
        $('#surName').val($data['surName']);
        $('#email').val($data['email']);
        $('#userName').val($data['userName']);
        $('#street').val($data['street']);
        $('#houseNumber').val($data['houseNumber']);
        $('#post').val($data['post']);
        $('#zipCode').val($data['zipCode']);
        $('#birthDate').val($data['birthDate']);
        $('#age').val($data['age']);
        $('#phone').val($data['phone']);
        $('#modal-editUser').modal('show');
    }
);


$(document).on('click', '[data-edit-contract]', function()
    {
        $('#modal-editContract').modal('show');
    }
);