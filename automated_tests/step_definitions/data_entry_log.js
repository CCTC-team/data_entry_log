const { Given } = require('@badeball/cypress-cucumber-preprocessor')

// Requests the export page directly rather than through its buttons, which only appear on the log page.
function requestExport(params = '') {
    return cy.url().then((url) => {
        const pid = new URL(url).searchParams.get('pid')
        return cy.window().then((win) => cy.request({
            url: `${win.app_path_webroot}ExternalModules/?prefix=data_entry_log&page=csv_export&pid=${pid}${params ? `&${params}` : ''}`,
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
 * @example the Data Entry Log export of {string} requested directly should contain {string}
 * @param {string} params - query string parameters for the export
 * @param {string} text - expected text
 * @description Verify the export with the given parameters contains the text
 */
Given("the Data Entry Log export of {string} requested directly should contain {string}", (params, text) => {
    requestExport(params).its('body').should('include', text)
})

/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the Data Entry Log export of {string} requested directly should NOT contain {string}
 * @param {string} params - query string parameters for the export
 * @param {string} text - text that should be absent
 * @description Verify the export with the given parameters does not contain the text
 */
Given("the Data Entry Log export of {string} requested directly should NOT contain {string}", (params, text) => {
    requestExport(params).its('body').should('not.include', text)
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

/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I set the Data Entry Log filter {string} to {string}
 * @param {string} id - id of the dropdown
 * @param {string} value - option value
 * @description Selects an option in a filter dropdown, which reloads the page
 */
Given("I set the Data Entry Log filter {string} to {string}", (id, value) => {
    cy.get(`#${id}`).should('not.be.disabled').select(value)
    cy.location('search').should('include', `${id}=${value}`)
})


/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I click the Data Entry Log paging button {string}
 * @param {string} id - id of the button
 * @description Clicks the next or previous page button
 */
Given("I click the Data Entry Log paging button {string}", (id) => {
    const pagenum = id === 'btnnextpage' ? 'pagenum=1' : 'pagenum=0'
    cy.document().its('readyState').should('eq', 'complete')
    cy.get(`#${id}`).should('not.be.disabled').then(($button) => $button[0].click())
    cy.location('search').should('include', pagenum)
})


/**
 * @module DataEntryLog
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I reset the Data Entry Log filters
 * @description Clicks the reset button on the filter form
 */
Given("I reset the Data Entry Log filters", () => {
    cy.document().its('readyState').should('eq', 'complete')
    cy.contains('button.clear-button', 'reset').then(($button) => $button[0].click())
    cy.location('search').should('not.include', 'defaulttimefilter=')
})
