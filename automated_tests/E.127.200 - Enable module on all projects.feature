Feature: E.127.200 - The system shall support the ability to enable Data Entry Log external module on all projects by default.

  As a REDCap end user
  I want to see that Data Entry Log is functioning as expected

Scenario: E.127.200 - Enable module on all projects by default
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    When I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Data Entry Log - v1.0.0"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled Enable for the external module named "Data Entry Log"
    And I click on the button labeled "Enable" in the dialog box
    Then I should see "Data Entry Log - v1.0.0"
    And I should NOT see "Enabled for All Projects"
    
    When I click on the button labeled exactly "Configure"
    And I check the checkbox labeled "Enable module on all projects by default"
    And I click on the button labeled "Save"
    Then I should see "Data Entry Log - v1.0.0"
    And I should see "Enabled for All Projects"
    And I logout
    
    Given I login to REDCap with the user "Test_User1"
    When I create a new project named "E.127.200" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdics_files/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Data Entry Log - v1.0.0"
    And I should see "Enabled for All Projects"
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