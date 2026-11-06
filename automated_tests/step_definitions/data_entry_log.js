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

Given("the Data Entry Log filter {string} should have the value {string}", (id, value) => {
    cy.get(`#${id}`).should('have.value', value)
})

Given("the Data Entry Log export requested directly should return status {int}", (status) => {
    requestExport().its('status').should('eq', status)
})

Given("the Data Entry Log export requested directly should contain {string}", (text) => {
    requestExport().its('body').should('include', text)
})

Given("the Data Entry Log export requested directly should NOT contain {string}", (text) => {
    requestExport().its('body').should('not.include', text)
})
