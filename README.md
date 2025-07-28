### Data Entry Log ###

The Data Entry log is designed to help users review and query data entered within a project. The existing Logging
feature provides limited help to Data managers who need to review recently entered data and may, for example, wish to
filter changes made to a form or field level.

This module simply accesses logs creating by the vanilla system and interrogates the log records sql statements. If 
there are no logs (for instance in a project that has been imported from xml with data), the module will be of limited
use.

#### System set up ####

Enabling the module at a system level will automatically create some functions and procedures as required;
1. Create the `GetDataEntryLogs` stored procedure in the REDCap database. This procedure is required to provide the log
   of data entries
1. Create the function `rh_split_string`, a utility function used by other procedures and functions
1. Create the function `GetInsertParts`, a function used to extract the inserts from the sql log entries
1. Create the function `GetUpdateParts`, a function used to extract the updates from the sql log entries
1. Create the function `GetDeleteParts`, a function used to extract the deletes from the sql log entries

Disabling the module at a system level will automatically drop the stored procedure and all functions as listed above.

The following project level settings are available;

- `max-days-all-records` - the maximum number of days available to view in the log when viewing all records. A sensible
  value is 31 to show all records in the last month. The performance impact of the query increases with the number. 
  Adjust the value to consider the impact. A project with many records and large amounts of data should use a smaller 
  number. Please note if a value greater than 365 is selected, it will default back to 31.
- `always-exclude-fields-with-regex` - providing a regular expression for this setting will result in any fields 
  matching the expression ALWAYS being removed from the list of log entries. For example, using a value such as
  '_monstat\$|_crfver\$' will exclude any fields whose names end with _monstat and _crfver
- `display-event-id-with-event-name` - when checked, the event names are shown with an event id suffix rather just name 
  i.e. 'Event 1 [23]' rather than simple 'Event 1'
- `display-arm-id-with-arm-name` - when checked, the arm names are shown with an arm id suffix rather just name
    i.e. 'Arm 2 [2]' rather than simple 'Arm 2'

#### Usage ####

- the module is only available to users who have the logging module permission (always available to superadmin)
- when the user is a member of a DAG, they can only see log entries for DAGS they have membership of	
- if the project does not use DAGs, the group column is hidden and there is no filtering option for groups
- if there are only single instances of Events (i.e. the default), then the event column is hidden as is the filter. If 
  the project has been set up for multiple events but there are no instances of multiple event logs, then the above will 
  still apply
- if there are only single Arms, then the arm column and filter are hidden
- users must either
	- select all records with a timeframe of no more than the number of days as given in `max-days-all-records`
	OR
	- select a single record and then can have unlimited timeframes
	
	- it is not possible to select every log entry in the project for the above reasons
-  the log includes an eyeball icon which is a link to the relevant form. Using the browser back button will return the
  user to the log with the same filters applied


#### Potential improvements ####

- amend stored procedure when filtering for values that have a text input and use a 'like' expression, and use a regex
  with 'rlike' in the stored procedure to make the filtering more flexible. This is an advanced option so users should
  still be able to use the basic filtering when required
- include csv export options