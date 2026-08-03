Feature: E.127.3100 - The system shall record configuration changes for the Data Entry Log external module (who, when, old->new) to the module's View Logs page.

  As a REDCap administrator
  I want every configuration change to be written to the module's External Module Logs
  So that there is an audit trail of who changed which setting, when, and from what value to what.

  Scenario: Enable external module from Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Data Entry Log - v1.1.1"
    When I click on the button labeled "Enable a module"
    And I wait for 2 seconds
    Then I should see "Available Modules"
    And I click on the button labeled "Enable" in the row labeled "Data Entry Log"
    And I wait for 1 second
    And I click on the button labeled "Enable"
    Then I should see "Data Entry Log - v1.1.1"

  Scenario: First configuration save logs the initial values
    Given I create a new project named "E.127.3100" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Data Entry Log - v1.1.1"
    Then I should see "Data Entry Log - v1.1.1"

    # First save has no prior snapshot, so each setting the admin actually sets is
    # logged as (empty) -> value. Blank settings stay empty and are not logged.
    # A checkbox gives a deterministic (empty) -> 1 entry, and the exclude-regex
    # textarea gives a real (empty) -> value entry so the old->new value capture is
    # exercised on a non-binary field.
    Given I click on the button labeled "Configure"
    Then I should see "Configure Module"
    When I check the checkbox labeled "If checked, arm names are suffixed with the arm ID e.g. 'Arm 1 [1]' rather than simply 'Arm 1'"
    And I enter "@EXCLUDE-ME" into the textarea field labeled "When given, any fields matching the given regex will always be excluded from the list of data entry logs"
    Then I click on the button labeled "Save"
    And I should see "Data Entry Log - v1.1.1"

    #VERIFY - the audit trail on the module's own View Logs page
    When I click on the link labeled "View Logs"
    Then I should see "External Module Logs"
    And I should see a table header and row containing the following values in a table:
      | Module         | Message                         | UserName   |
      | data_entry_log | Configuration changed (project) | Test_Admin |

    # The hook logs one entry per changed key in config.json order, so newest-first
    # the FIRST button is display-arm-id-with-arm-name (logged last) and the SECOND
    # button is always-exclude-fields-with-regex. old->new live in params
    # 'old_value'/'new_value'; 'setting' names the changed key. The acting user is the
    # UserName column, not a param.
    When I click on the first button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                        |
      | setting   | display-arm-id-with-arm-name |
      | old_value | (empty)                      |
      | new_value | 1                            |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

    When I click on the second button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                            |
      | setting   | always-exclude-fields-with-regex |
      | old_value | (empty)                          |
      | new_value | @EXCLUDE-ME                      |

  Scenario: Changing a setting logs an old->new audit entry
    # rctf starts each scenario from a clean browser page, so re-navigate to the
    # project fresh (same pattern as the other continuation scenarios).
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.127.3100"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Data Entry Log - v1.1.1"

    # Change the exclude-regex from "@EXCLUDE-ME" to "@EXCLUDE-ALL". This is a genuine
    # value -> value transition, proving the snapshot/diff works across saves (not just
    # first save) and captures the real old and new values.
    When I click on the button labeled "Configure"
    Then I should see "Configure Module"
    And I clear field and enter "@EXCLUDE-ALL" into the textarea field labeled "When given, any fields matching the given regex will always be excluded from the list of data entry logs"
    Then I click on the button labeled "Save"
    And I should see "Data Entry Log - v1.1.1"

    #VERIFY - the audit trail on the module's own View Logs page
    When I click on the link labeled "View Logs"
    Then I should see "External Module Logs"
    And I should see a table header and row containing the following values in a table:
      | Module         | Message                         | UserName   |
      | data_entry_log | Configuration changed (project) | Test_Admin |

    # old->new values live in admin-gated parameters. The most recent entry is the
    # always-exclude-fields-with-regex @EXCLUDE-ME -> @EXCLUDE-ALL change.
    When I click on the first button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                            |
      | setting   | always-exclude-fields-with-regex |
      | old_value | @EXCLUDE-ME                      |
      | new_value | @EXCLUDE-ALL                     |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

    # Disable the external module from the Control Center
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Data Entry Log - v1.1.1"

    # Verify no exceptions are thrown in the system
    Given I open Email
    Then I should NOT see an email with subject "REDCap External Module Hook Exception - data_entry_log"
