function fillAccount(email) {
    document.getElementById('email').value = email;
    document.getElementById('password').focus();
}

function removeAccount(email, event) {
    event.stopPropagation();
    const selector = '.saved-account-circle[data-email="' + email.replace(/"/g, '&quot;') + '"]';
    const circle = document.querySelector(selector);
    if (circle) {
        circle.classList.add('removing');
        setTimeout(() => {
            actuallyRemoveAccount(email);
            circle.remove();
        }, 300);
        return;
    }
    actuallyRemoveAccount(email);
}

function actuallyRemoveAccount(email) {
    let savedAccounts = [];
    const cookie = getCookie('linknet_saved_accounts');
    if (cookie) {
        savedAccounts = JSON.parse(cookie);
    }
    savedAccounts = savedAccounts.filter(account => account.email !== email);
    document.cookie = 'linknet_saved_accounts=' + JSON.stringify(savedAccounts) + '; path=/; max-age=' + (30 * 24 * 60 * 60);
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
}