// Get infixRow and firstNameRow elements
const infixRow = document.querySelector('.user-infix-name-wrap');
const firstNameRow = document.querySelector('.user-first-name-wrap');

// Check if both elements exist
if (infixRow && firstNameRow) {
    // Get the table element
    const table = document.querySelector('.user-infix-table');

    // Insert the infixRow after the firstNameRow
    firstNameRow.parentNode.insertBefore(infixRow, firstNameRow.nextSibling);
    
    // Remove the table element if it exists
    if (table) {
        table.remove();
    }
}