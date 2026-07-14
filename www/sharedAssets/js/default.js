$(document).on('click', '[data-copy-to-clipboard]', function()
    {
        let text = $(this).text();
        const textArea = document.createElement("textarea");
        textArea.value=text;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        document.execCommand('copy')
        document.body.removeChild(textArea);
    }
);
