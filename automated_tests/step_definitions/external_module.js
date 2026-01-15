//Add any of your own step definitions here
const { Given, defineParameterType } = require('@badeball/cypress-cucumber-preprocessor')


defineParameterType({
    name: 'externalOption',
    regexp: /Enable|Delete Version|Request Activation/
})


defineParameterType({
    name: 'emTableName',
    regexp: /monitoring logging|data entry log|system changes|project changes|user role changes/
})

emTableName = {
    'monitoring logging' : '#monitor-query-data-log',
    'data entry log' : '#log-data-entry-event',
    'system changes' : '#system_changes_table',
    'project changes' : '#project_changes_table',
    'user role changes' : '#user_role_changes_table',
}

/**
 * @module ExternalModule
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I {externalOption} the external module named {string}
 * @param {string} externalOption - available options - 'Enable', 'Delete Version'
 * @param {string} label - name of external module
 * @description Enable/Disable external module
 */
Given("I click on the button labeled {externalOption} for the external module named {string}", (option, label) => {
    cy.get('#external-modules-disabled-table').find('td').contains(label).parents('tr').within(() => {
        cy.get('button').contains(option).click()
    })
})


/**
 * @module ExternalModule
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I click on the button labeled {string} for the field labeled {string} in the external module configuration
 * @param {string} buttonLabel - Label on button
 * @param {string} field - Field Label
 * @description Clicks on the button for the field in the external module configuration
 */
Given("I click on the button labeled {string} for the field labeled {string} in the external module configuration", (buttonLabel, field) => {
    cy.get('.table-no-top-row-border').find('td').contains(field).parents('tr').within(() => {
        cy.get('button').contains(buttonLabel).click()
    })
})


/**
 * @module ExternalModule
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I close the dialog box for the external module {string}
 * @param {string} name - Name of external module
 * @description Close the dialog box for the external module
 */
Given("I close the dialog box for the external module {string}", (name) => {
    cy.get('.modal-dialog').contains(name).parents('div[class="modal-header"]').within(() => {
        cy.get('button[class=close]').click()
    })
})


/**
 * @module ControlCenter
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I click on the {toDoTableIcons} icon for the {string} request created for the project named {string} within the {toDoTableTypes} table
 * @param {string} toDoTableIcons - available options: 'process request', 'get more information', 'add or edit a comment', 'Move to low priority section', 'archive request notification'
 * @param {string} request_name - Name of request
 * @param {string} project_name - the text value of project name you want to target
 * @param {string} toDoTableTypes - available options: 'Pending Requests', 'Low Priority Pending Requests', 'Completed & Archived Requests'
 * @description Clicks on an icon within the To-Do-List page based upon Icon, Request Name, Project Name, and Table Name specified.
 */
Given('I click on the {toDoTableIcons} icon for the {string} request created for the project named {string} within the {toDoTableTypes} table', (icon, request_name, project_name, table_name) => {
    cy.get(`.${window.toDoListTables[table_name]}`).within(() => {
        cy.get(`.request-container:contains("${project_name}"):has(.type:contains("${request_name}"))`).within(() => {
            cy.get(`button[data-tooltip="${icon}"]`).click()
        })
    })
})


/**
 * @module ControlCenter
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I (should )see the {string} request created for the project named {string} within the {toDoTableTypes} table
 * @param {string} request_name - Name of request
 * @param {string} project_name - the text value of project name you want to target
 * @param {string} toDoTableTypes - available options: 'Pending Requests', 'Low Priority Pending Requests', 'Completed & Archived Requests'
 * @description Identifies Request Name within the To-Do-List page based upon Project Name, and Table Name specified.
 */
Given('I (should )see the {string} request created for the project named {string} within the {toDoTableTypes} table', (request_name, project_name, table_name) => {
    cy.get(`.${window.toDoListTables[table_name]}`).within(() => {
        cy.get(`.request-container:contains("${project_name}"):has(.type:contains("${request_name}"))`)
    })
})


/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should see {int} row(s) in the {emTableName} table
 * @param {int} num - number of row(s)
 * @param {string} emTableName - available options: 'monitoring logging', 'data entry log', 'system changes', 'project changes', 'user role changes'
 * @description verifies the table contains the specified number of row(s)
 */
Given('I should see {int} row(s) in the {emTableName} table', (num, tableName) => {
    element = emTableName[tableName] + ' tbody tr'
    cy.get(element).its('length').then ((rowCount) => {
        // Subtracting 1 for header
        if (tableName === 'data entry log') {
            rowCount = rowCount-1
        }

        if (tableName === 'monitoring logging') {
             // Subtracting 1 for header and dividing by 2 as each entry has 2 rows
            rowCount = (rowCount-1)/2
        }

        expect(rowCount).to.be.equal(num)
    })
})