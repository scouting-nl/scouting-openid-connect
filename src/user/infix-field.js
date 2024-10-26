var infixRow = document.querySelector('.user-infix-name-wrap');
var firstNameRow = document.querySelector('.user-first-name-wrap');
if (infixRow && firstNameRow) {
    var table = document.querySelector('.user-infix-table');
    firstNameRow.parentNode.insertBefore(infixRow, firstNameRow.nextSibling);
    if (table) {
        table.remove();
    }
}