fetch('../mainpage/seshCheck.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (!data.loggedIn) {
            window.location.replace('../mainpage/index.php');
        }
    })
    .catch(() => {
        window.location.replace('../mainpage/index.php');
    });