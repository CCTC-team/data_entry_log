Feature: E.127.3000 - The system shall support the ability to configure 'Module configuration permissions in projects' for Data Entry Log external module.

  As a REDCap end user
  I want to see that Data Entry Log is functioning as expected

Scenario: E.127.3000 - Module configuration permissions in projects
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    When I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Data Entry Log - v1.0.0"
    When I click on the button labeled "Enable a module"
    And I wait for 2 seconds
    Then I should see "Available Modules"
    And I click on the button labeled "Enable" in the row labeled "Data Entry Log"
    And I wait for 1 second
    And I click on the button labeled "Enable"
    Then I should see "Data Entry Log - v1.0.0"
    
    When I click on the button labeled "Configure"
    Then I should see the dropdown field labeled "Module configuration permissions in projects" with the option "Require Project Setup/Design privilege" selected
    And I click on the button labeled "Save"
    Then I should see "Data Entry Log - v1.0.0"

    When I create a new project named "E.127.3000" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/Project_redcap_val_nodata.xml", and clicking the "Create Project" button

    # Enable external module
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Data Entry Log - v1.0.0"
    Then I should see "Data Entry Log - v1.0.0"

    #VERIFY
    When I click on the link labeled "User Rights"
    And I enter "Test_User1" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    When I check the User Right named "Project Setup & Design"
    Then I should see a checkbox labeled "Data Entry Log" that is checked
    And I click on the button labeled "Add user"
    Then I should see "successfully added"

    # Enable - Require module-specific user privilege
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I should see "Data Entry Log - v1.0.0"
    When I click on the button labeled "Configure"
    And I select "Require module-specific user privilege" on the dropdown field labeled "Module configuration permissions in projects"
    And I click on the button labeled "Save"
    Then I should see "Data Entry Log - v1.0.0"

    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.3000"

    #VERIFY
    When I click on the link labeled "User Rights"
    And I enter "Test_User2" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    When I check the User Right named "Project Setup & Design"
    Then I should see a checkbox labeled "Data Entry Log" that is unchecked
    And I check the checkbox labeled "Data Entry Log"
    And I click on the button labeled "Add user"
    Then I should see "successfully added"

Scenario: E.127.600 - View Usage of the external module
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "Data Entry Log - v1.0.0"
    When I click on the button labeled "View Usage"
    And I should see a link labeled "E.127.3000"
    When I click on the link labeled "E.127.3000"
    Then I should see "Project Home"
    And I should see "E.127.3000"

    # Disable external module in Control Center
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Data Entry Log - v1.0.0"
    And I logout

    # Verify no exceptions are thrown in the system
    Given I open Email
    Then I should NOT see an email with subject "REDCap External Module Hook Exception - data_entry_log"