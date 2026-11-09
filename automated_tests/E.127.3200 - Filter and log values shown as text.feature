Feature: E.127.3200 - The system shall show filter values and logged data values as plain text, and limit the export to users with Logging rights, in the Data Entry Log external module.

  As a REDCap end user
  I want values containing quotes or HTML to be displayed exactly as they were entered
  So that the content of a value cannot change how the Data Entry Log page or its export behaves

  Scenario: Enable external module from Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Data Entry Log - v1.1.2"
    When I click on the button labeled "Enable a module"
    And I wait for 2 seconds
    Then I should see "Available Modules"
    And I click on the button labeled "Enable" in the row labeled "Data Entry Log"
    And I wait for 1 second
    And I click on the button labeled "Enable"
    Then I should see "Data Entry Log - v1.1.2"

  Scenario: Enable external module in project
    Given I create a new project named "E.127.3200" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I click on the second link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Data Entry Log - v1.1.2"
    Then I should see "Data Entry Log - v1.1.2"

    # Test_User1 can see the project but is not given the Logging right
    Given I click on the link labeled "User Rights"
    And I enter "Test_User1" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    And I click on the button labeled "Add user"
    Then I should see "successfully added"

  Scenario: Logged data values containing quotes and HTML are shown as entered
    Given I click on the link labeled "Add / Edit Records"
    When I click on the button labeled "Add new record for the arm selected above"
    And I click the bubble to add a record for the "Data Types" longitudinal instrument on event "Event 1"
    Then I enter "O'Brien <b>bold</b>" into the data entry form field labeled "Name"
    And I enter "<0.5" into the data entry form field labeled "Text2"
    And I enter "=1+1" into the data entry form field labeled "Text Box"
    And I click on the button labeled "Save & Exit Form"
    Then I should see "Record Home Page"

    When I click on the link labeled "Data Entry Log"
    Then I should see "O'Brien <b>bold</b>"
    And I should see "<0.5"

  Scenario: A filter value containing quotes and HTML stays inside its input
    Given I click on the link labeled "Data Entry Log"
    When I enter "x'><b>reflected</b>" into the input field labeled "New value"
    And I wait for 1 second
    And I click on the button labeled "Custom range"
    Then I should see "No log entries found"
    And I should NOT see "reflected"
    And the Data Entry Log filter "newdatavalue" should have the value "x'><b>reflected</b>"

  Scenario: Filtering on a value containing HTML still finds it
    Given I click on the link labeled "Data Entry Log"
    When I enter "<b>bold" into the input field labeled "New value"
    And I wait for 1 second
    And I click on the button labeled "Custom range"
    Then I should see 1 row in the data entry log table
    And I should see "O'Brien <b>bold</b>"
    And I should NOT see "<0.5"
    And the Data Entry Log filter "newdatavalue" should have the value "<b>bold"

  Scenario: The export is refused for a user without Logging rights
    # Leave the log page first, because login returns to the page that was open
    Given I click on the link labeled "My Projects"
    And I logout
    And I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.3200"
    Then I should see "Project Home and Design"
    And I should NOT see "Data Entry Log"
    And the Data Entry Log export requested directly should return status 403
    And the Data Entry Log export requested directly should NOT contain "O'Brien"
    And I logout

  Scenario: The export protects spreadsheet users from formula values
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.3200"
    And I click on the link labeled "Data Entry Log"
    Then the Data Entry Log export requested directly should return status 200
    And the Data Entry Log export requested directly should contain " =1+1"
    And the Data Entry Log export requested directly should NOT contain ",=1+1"
    And the Data Entry Log export requested directly should contain "<0.5"

  Scenario: The export applies the filters from the log page
    Given I click on the link labeled "Data Entry Log"
    Then the Data Entry Log export of "newdatavalue=bold&export_type=current_page" requested directly should contain "<b>bold</b>"
    And the Data Entry Log export of "newdatavalue=bold&export_type=current_page" requested directly should NOT contain "=1+1"

  Scenario: Export everything ignores the arm filter
    Given I click on the link labeled "Data Entry Log"
    Then the Data Entry Log export of "dataarm=999&export_type=everything" requested directly should contain "<b>bold</b>"

  Scenario: Disable external module
    Given I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Data Entry Log - v1.1.2"
