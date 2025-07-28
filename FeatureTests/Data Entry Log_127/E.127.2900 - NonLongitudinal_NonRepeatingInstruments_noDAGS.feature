Feature: E.127.2900 - NonLongitudinal_NonRepeatingInstruments_noDAGS
  
  As a REDCap end user
  I want to see that Data Entry Log External Module work as expected

  Scenario: Enable external Module from Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    # EMAIL ADDRESS SET FOR REDCAP ADMIN - without it, emails are not send out from system
    When I click on the link labeled "General Configuration"
    Then I should see "General Configuration"
    When I enter "redcap@test.instance" into the input field labeled "Email Address of REDCap Administrator"
    And I click on the button labeled "Save Changes"
    Then I should see "Your system configuration values have now been changed"

    Given I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Data Entry Log - v0.0.0"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled Enable for the external module named "Data Entry Log"
    And I click on the button labeled "Enable" in the dialog box
    Then I should see "Data Entry Log - v0.0.0"
 
  Scenario: Enable external module in project
    Given I create a new project named "E.127.2900" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "redcap_val/ProjectTypes/NonLongitudinal_NonRepeatingInstruments_noDAGS.xml", and clicking the "Create Project" button
    When I click on the link labeled "DAGs"
    Then I should NOT see "DAG1"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled Enable for the external module named "Data Entry Log - v0.0.0"
    Then I should see "Data Entry Log - v0.0.0"

    When I click on the button labeled exactly "Configure"
    Then I should see "Configure Module"
    When I enter "10" into the input field labeled "The maximum number of days permitted when not limiting the records being queried" in the dialog box
    And I click on the button labeled "Save" in the dialog box
    Then I should see "Data Entry Log - v0.0.0"

    # Add User Test_User1 with 'Project Setup & Design' rights
    Given I click on the link labeled "User Rights"
    And I enter "Test_User1" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    And I check the User Right named "Logging"
    And I click on the button labeled "Add user"
    Then I should see "successfully added"
    And I logout

  Scenario: E.127.1200, E.127.1300 - Data Entry Log for NonRepeating Instruments in DAG1
    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.2900"
    When I click on the link labeled "Data Entry Log"
    Then I should see "No log entries found"

    # Instruments 1
    Given I click on the link labeled "Add / Edit Records"
    When I click on the button labeled "Add new record"
    And I click the bubble to add a record for the "Data Types" instrument on event "Status"
    Then I enter "1" into the data entry form field labeled "CRF Versioning"
    Then I enter "User1" into the data entry form field labeled "Name"
    And I click on the button labeled "Save & Exit Form"
    Then I should see "Record Home Page"

    When I click on the link labeled "Data Entry Log"
    Then I should see a table header and rows containing the following values in the a table:
      |  Date / Time      | Username   | Record ID | Instance | Form       | Field and Label                      | New Value         | Action        |
      |  mm/dd/yyyy hh:mm | test_user1 | 1         |          | data_types | data_types_crfver [CRF Versioning]   | 1                 | Create record |
      |  mm/dd/yyyy hh:mm | test_user1 | 1         |          | data_types | ptname [Name]	                      | User1           	| Create record |
      |  mm/dd/yyyy hh:mm | test_user1 | 1         |          | data_types | data_types_complete [Complete?]      | 0                 | Create record |

    And I should see 3 rows in the data entry log table

    # Instruments 2
    Given I click on the link labeled "Record Status Dashboard"
    And I click on the link labeled "1"
    Then I should see "Record Home Page"
    And I click the bubble to add a record for the "Text Validation" instrument on event "Status"
    Then I enter "1" into the data entry form field labeled "CRF Versioning"
    Then I enter "testuser1@abc.com" into the data entry form field labeled "Email"
    And I click on the button labeled "Save & Exit Form"
    Then I should see "Record Home Page"

    When I click on the link labeled "Data Entry Log"
    Then I should see a table header and rows containing the following values in the a table:
      | Username   | Record ID | Instance | Form            | Field and Label                         | New Value         | Action        |
      | test_user1 | 1         |          | text_validation | text_validation_crfver [CRF Versioning] | 1                 | Update record |
      | test_user1 | 1         |          | text_validation | email_v2 [Email]	                      | testuser1@abc.com	| Update record |
      | test_user1 | 1         |          | text_validation | text_validation_complete [Complete?]    | 0                 | Update record |
      | test_user1 | 1         |          | data_types      | data_types_crfver [CRF Versioning]      | 1                 | Create record |
      | test_user1 | 1         |          | data_types      | ptname [Name]	                          | User1           	| Create record |
      | test_user1 | 1         |          | data_types      | data_types_complete [Complete?]         | 0                 | Create record |
 
    And I should see 6 rows in the data entry log table
    And I logout

  Scenario: E.127.100 - Disable external module
    # Disable external module in project
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.2900"
    When I click on the link labeled "View Logs"
    Then I should see "No data available in table"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Data Entry Log - v0.0.0"
    When I click on the button labeled exactly "Disable"
    Then I should see "Disable module?" in the dialog box
    When I click on the button labeled "Disable module" in the dialog box
    Then I should NOT see "Data Entry Log - v0.0.0"

    # Disable external module in Control Center
    Given I click on the link labeled "Control Center"
    When I click on the link labeled exactly "Manage"
    And I click on the button labeled exactly "Disable"
    Then I should see "Disable module?" in the dialog box
    When I click on the button labeled "Disable module" in the dialog box
    Then I should NOT see "Data Entry Log - v0.0.0"
    And I logout

    # Verify no exceptions are thrown in the system
    Given I open Email
    Then I should NOT see an email with subject "REDCap External Module Hook Exception - data_entry_log"