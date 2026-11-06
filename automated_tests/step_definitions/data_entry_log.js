const { Given } = require('@badeball/cypress-cucumber-preprocessor')

// Requests the export page directly rather than through its buttons, which only appear on the log page.
function requestExport() {
    return cy.url().then((url) => {
        const pid = new URL(url).searchParams.get('pid')
        return cy.window().then((win) => cy.request({
            url: `${win.app_path_webroot}ExternalModules/?prefix=data_entry_log&page=csv_export&pid=${pid}`,
            failOnStatusCode: false
        }))
    })
}

/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the Data Entry Log filter {string} should have the value {string}
 * @param {string} id - id of the filter input
 * @param {string} value - expected value
 * @description Verify the value shown by a filter input
 */
Given("the Data Entry Log filter {string} should have the value {string}", (id, value) => {
    cy.get(`#${id}`).should('have.value', value)
})

/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the Data Entry Log export requested directly should return status {int}
 * @param {int} status - expected HTTP status
 * @description Verify the HTTP status returned when the export page is requested directly
 */
Given("the Data Entry Log export requested directly should return status {int}", (status) => {
    requestExport().its('status').should('eq', status)
})

/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the Data Entry Log export requested directly should contain {string}
 * @param {string} text - expected text
 * @description Verify the export contains the text when the export page is requested directly
 */
Given("the Data Entry Log export requested directly should contain {string}", (text) => {
    requestExport().its('body').should('include', text)
})

/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the Data Entry Log export requested directly should NOT contain {string}
 * @param {string} text - text that should be absent
 * @description Verify the export does not contain the text when the export page is requested directly
 */
Given("the Data Entry Log export requested directly should NOT contain {string}", (text) => {
    requestExport().its('body').should('not.include', text)
})
