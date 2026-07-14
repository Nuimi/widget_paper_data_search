$.getScript("/www/admin/assets/js/defaults/user.js");

/**
 * Default
 */

    function showLoaderPage()
    {
        $('#loader-page').removeClass('hidden');
    }

    function hideLoaderPage()
    {
        $('#loader-page').addClass( "hidden" );
    }

(function() {
    'use strict';
    window.addEventListener('load', function()
    {
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.getElementsByClassName('needs-validation');
        // Loop over them and prevent submission
        var validation = Array.prototype.filter.call(forms, function(form)
        {
            form.addEventListener('submit', function(event)
            {
                if (form.checkValidity() === false)
                {
                    event.preventDefault();
                    event.stopPropagation();
                }

                if (form.contains(document.getElementById('n_video')))
                {
                    var videoInput = document.getElementById('n_video');
                    if (videoInput.files[0] && videoInput.files[0].size > 50000000)
                    {
                        event.preventDefault();
                        event.stopPropagation();
                        videoInput.classList.add('is-invalid');
                        document.getElementById('videoSizeError').style.display = 'block';
                    } else {
                        videoInput.classList.remove('is-invalid');
                        document.getElementById('videoSizeError').style.display = 'none';
                    }
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })

/**
 * ---Default
 */