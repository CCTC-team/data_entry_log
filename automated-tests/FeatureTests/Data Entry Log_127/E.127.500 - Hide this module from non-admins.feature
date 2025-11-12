Feature: E.127.500 - The system shall support the ability to hide Data Entry Log external module from non-admins in the list of enabled modules on each project.

  As a REDCap end user
  I want to see that Data Entry Log is functioning as expected

Scenario: E.127.500 - Hide this module from non-admins in the list of enabled modules on each project
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    When I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Data Entry Log - v1.0.0"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled Enable for the external module named "Data Entry Log"
    And I click on the button labeled "Enable" in the dialog box
    Then I should see "Data Entry Log - v1.0.0"
    
    When I click on the button labeled exactly "Configure"
    And I check the checkbox labeled "Hide this module from non-admins in the list of enabled modules on each project"
    And I click on the button labeled "Save"
    Then I should see "Data Entry Log - v1.0.0"
  
    When I create a new project named "E.127.500" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "redcap_val/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    
    # Enable external module
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled Enable for the external module named "Data Entry Log - v1.0.0"
    Then I should see "Data Entry Log - v1.0.0"

    # Add User Test_User1 with Project Setup & Design User Rights
    When I click on the link labeled "User Rights"
    And I enter "Test_User1" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    Then I check the User Right named "Project Setup & Design"
    And I click on the button labeled "Add user"
    Then I should see "successfully added"
    And I logout

    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Data Entry Log - v1.0.0"
    And I logout

    # Disable 'Hide this module from non-admins in the list of enabled modules on each project'
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    Then I should see "Data Entry Log - v1.0.0"
    When I click on the button labeled exactly "Configure"
    And I uncheck the checkbox labeled "Hide this module from non-admins in the list of enabled modules on each project"
    And I click on the button labeled "Save"
    Then I should see "Data Entry Log - v1.0.0"
    And I logout

    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Data Entry Log - v1.0.0"
    And I logout

    # Enable from project - 'Hide this module from non-admins in the list of enabled modules on each project'
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    Then I should see "Data Entry Log - v1.0.0"
    When I click on the button labeled exactly "Configure"
    And I check the checkbox labeled "Hide this module from non-admins in the list of enabled modules on this project"
    And I enter "10" into the input field labeled "The maximum number of days permitted when not limiting the records being queried" in the dialog box
    And I click on the button labeled "Save"
    Then I should see "Data Entry Log - v1.0.0"
    And I logout

    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Data Entry Log - v1.0.0"
    And I logout

    # Disable external module in Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    And I click on the button labeled exactly "Disable"
    Then I should see "Disable module?" in the dialog box
    When I click on the button labeled "Disable module" in the dialog box
    Then I should NOT see "Data Entry Log - v1.0.0"
    And I logout

    # Verify no exceptions are thrown in the system
    Given I open Email
    Then I should NOT see an email with subject "REDCap External Module Hook Exception - data_entry_log"